<?php

namespace StreetMesh\Chess\Tests;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use StreetMesh\Chess\ChessExperience;
use StreetMesh\Chess\Games;
use StreetMesh\Protocol\Laravel\Permissions\Delegation;
use StreetMesh\Protocol\Network;
use StreetMesh\Protocol\P256;
use StreetMesh\Protocol\Scope;
use StreetMesh\Venue\Gatherings\Gathering;
use StreetMesh\Venue\Gatherings\Settling;
use StreetMesh\Venue\Visitors;

/**
 * A finished game ending up in the players' own records.
 *
 * The last piece of v0, and the only one that makes the rest worth building: a
 * game of chess between two people on different servers, where each of them
 * ends up holding their own verifiable record of it.
 *
 * The hub decided what happened and cannot sign it — it holds no key. The venue
 * can sign and did not watch. This is where the two meet, and the tests here
 * are mostly about what a browser is *not* trusted with.
 */
class SettleTest extends TestCase
{
    /**
     * The hub, answering about one room and nothing else.
     */
    private function hubSaying(?array $result): void
    {
        $this->app->instance(Network::class, new class($result) implements Network
        {
            public function __construct(private readonly ?array $result) {}

            public function get(string $url): ?string
            {
                if (! str_contains($url, '/result?room=')) {
                    return null;
                }

                return $this->result === null ? '{}' : (string) json_encode($this->result);
            }

            public function txt(string $name): array
            {
                return [];
            }

            public function post(string $url, string $body, array $headers = []): array
            {
                return ['status' => 200, 'body' => ''];
            }
        });
    }

    private function player(string $who): Delegation
    {
        return Delegation::create([
            'did' => 'did:web:'.$who.'.home.test',
            'handle' => $who.'.home.test',
            'issuer' => 'https://'.$who.'.home.test',
            'dpop_key' => Delegation::store(P256::generate()),
            'access_token' => 'a-live-token',
            'scope' => 'atproto '.Scope::forRepo([ChessExperience::COLLECTION], [Scope::CREATE]),
            'expires_at' => now()->addMinutes(15),
        ]);
    }

    private function game(): Gathering
    {
        $white = $this->player('alice');
        $game = $this->app->make(Games::class)->open($white);
        $this->app->make(Games::class)->join($game, $this->player('bob'));

        // Somebody has to be at the door to knock on it.
        session()->put(Visitors::SESSION_KEY, $white->id);

        return $game;
    }

    /**
     * @return array<string, mixed>
     */
    private function finished(): array
    {
        return [
            'outcome' => 'checkmate',
            'winner' => 'white',
            'moves' => ['e4', 'e5', 'Qh5', 'Nc6', 'Bc4', 'Nf6', 'Qxf7#'],
            'fen' => 'r1bqkb1r/pppp1Qpp/2n2n2/4p3/2B1P3/8/PPPP1PPP/RNB1K1NR b KQkq - 0 4',
        ];
    }

    /**
     * A browser saying "it is over" does not make it over. The venue asks the
     * hub, and a game still being played answers nothing — so the worst anybody
     * can do by knocking early is make this server ask a question.
     */
    public function test_a_game_that_is_not_over_settles_nothing(): void
    {
        Http::fake();
        $this->hubSaying(null);
        $game = $this->game();

        $this->post(route('chess.settle', $game->key))
            ->assertOk()
            ->assertJson(['settled' => false, 'because' => 'not over']);

        $this->assertTrue($game->fresh()?->isOpen());
        Http::assertNothingSent();
    }

    /**
     * Two records, one each, rather than one shared one. There is no shared
     * place for one to live: the players are on different servers, and after
     * tonight this venue may not exist.
     */
    public function test_a_finished_game_is_written_to_both_players_own_servers(): void
    {
        $written = [];

        Http::fake(function ($request) use (&$written) {
            $written[] = $request->url();

            return Http::response(['uri' => 'at://somebody/com.streetmesh.games.chess/1', 'cid' => 'bafy'], 200);
        });

        $this->hubSaying($this->finished());
        $game = $this->game();

        $this->post(route('chess.settle', $game->key))
            ->assertOk()
            ->assertJson(['settled' => true]);

        $this->assertCount(2, array_filter($written, fn (string $url): bool => str_contains($url, 'createRecord')));
        $this->assertFalse($game->fresh()?->isOpen(), 'a settled game is over');
    }

    /**
     * Every board at the table will knock, which is the point — it only takes
     * one of them still being open. The venue has to ignore the rest: a
     * repository is append-only, so a second record of the same game could
     * never be taken back.
     */
    public function test_knocking_twice_does_not_write_a_second_record(): void
    {
        Http::fake(fn () => Http::response(['uri' => 'at://somebody/x/1', 'cid' => 'bafy'], 200));

        $this->hubSaying($this->finished());
        $game = $this->game();

        $this->post(route('chess.settle', $game->key))->assertOk();

        $before = Http::recorded()->count();

        $this->post(route('chess.settle', $game->key))
            ->assertOk()
            ->assertJson(['settled' => true, 'already' => true]);

        $this->assertSame($before, Http::recorded()->count(), 'nothing further was written');
    }

    /**
     * The hub forgets a room when its last player leaves, so the venue's copy
     * is the only thing left that knows a game happened. Without it a finished
     * board is one this screen cannot draw, and a list of what has been played
     * here is a list of names.
     */
    public function test_settling_keeps_how_it_ended(): void
    {
        Http::fake(fn () => Http::response(['uri' => 'at://somebody/x/1', 'cid' => 'bafy'], 200));

        $this->hubSaying($this->finished());
        $game = $this->game();

        $this->post(route('chess.settle', $game->key))->assertOk();

        $kept = $game->fresh()?->outcome;

        $this->assertSame('checkmate', $kept['outcome']);
        $this->assertSame('white', $kept['winner']);
        $this->assertSame($this->finished()['moves'], $kept['moves']);
    }

    /**
     * Writing a record means calling each player's own server, and whoever
     * brought the news should not be holding a request open while this venue
     * waits on somebody else's afternoon.
     *
     * Both messengers take the same route — a browser noticing the board has
     * stopped, and the hub announcing the room has ended — so one job covers a
     * gathering however many of them arrive.
     */
    public function test_settling_is_handed_to_the_queue(): void
    {
        Queue::fake();
        $this->hubSaying($this->finished());
        $game = $this->game();

        $this->post(route('chess.settle', $game->key))
            ->assertOk()
            ->assertJson(['settled' => true, 'queued' => true]);

        Queue::assertPushed(
            Settling::class,
            fn (Settling $job): bool => $job->gathering->is($game) && $job->result['winner'] === 'white',
        );
    }

    /**
     * A domicile that is down is a record that arrives late rather than one
     * that never arrives, which is the whole reason this is on a queue.
     */
    public function test_a_settling_that_fails_is_tried_again(): void
    {
        $job = new Settling($this->game(), $this->finished());

        $this->assertGreaterThan(1, $job->tries);
        $this->assertNotEmpty($job->backoff);
    }

    public function test_a_game_that_does_not_exist_is_not_settled(): void
    {
        $this->hubSaying($this->finished());
        $this->game();

        $this->post(route('chess.settle', 'no-such-game'))->assertNotFound();
    }
}
