<?php

namespace StreetMesh\Chess\Tests;

use Illuminate\Support\Facades\Http;
use StreetMesh\Chess\ChessExperience;
use StreetMesh\Chess\Games;
use StreetMesh\Protocol\Laravel\Permissions\Delegation;
use StreetMesh\Protocol\P256;
use StreetMesh\Protocol\Scope;
use StreetMesh\Venue\Gatherings\Gathering;
use StreetMesh\Venue\Visitors;

/**
 * Chess as the venue sees it.
 *
 * Nothing here knows how a knight moves — that is the hub's business, and
 * deliberately not duplicated, because two implementations of the rules are two
 * chances to disagree about who won. What is tested is everything the rules are
 * not: that a game exists, who is at it, and that when it ends each player ends
 * up holding their own record of it.
 */
class GameTest extends TestCase
{
    private function games(): Games
    {
        return $this->app->make(Games::class);
    }

    private function player(string $who): Delegation
    {
        return Delegation::create([
            'did' => 'did:web:'.$who.'.home.test',
            'handle' => $who.'.home.test',
            'issuer' => 'https://'.$who.'.home.test',
            'dpop_key' => Delegation::store(P256::generate()),
            'access_token' => 'a-live-token',
            'refresh_token' => 'a-refresh-token',
            'scope' => 'atproto '.Scope::forRepo([ChessExperience::COLLECTION], [Scope::CREATE]),
            'expires_at' => now()->addMinutes(15),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function finished(array $overrides = []): array
    {
        return [
            'outcome' => 'checkmate',
            'winner' => 'white',
            'moves' => ['e4', 'e5', 'Bc4', 'Nc6', 'Qh5', 'Nf6', 'Qxf7#'],
            'fen' => 'r1bqkb1r/pppp1Qpp/2n2n2/4p3/2B1P3/8/PPPP1PPP/RNB1K1NR b KQkq - 0 4',
            ...$overrides,
        ];
    }

    public function test_opening_a_game_seats_whoever_opened_it(): void
    {
        $game = $this->games()->open($this->player('alice'));

        $this->assertSame(ChessExperience::COLLECTION, $game->experience);
        $this->assertSame('white', (string) $game->seats()->value('seat'));
    }

    public function test_the_second_person_takes_the_other_chair(): void
    {
        $games = $this->games();
        $game = $games->open($this->player('alice'));

        $this->assertSame('black', $games->join($game, $this->player('bob'))->seat);
    }

    /**
     * A game whose white player gave their permission back.
     *
     * A seat belongs to a delegation and goes with it, so revoking leaves a
     * game with a black player and no white — a shape the lobby had never been
     * shown. It read `$players['white']` directly, an undefined key is an
     * exception once debug is off, and so the lobby returned 500 to everybody,
     * over one visitor's revoked permission.
     */
    public function test_the_lobby_survives_a_game_whose_player_revoked(): void
    {
        $games = $this->games();

        $white = $this->player('alice');
        $black = $this->player('bob');

        $game = $games->open($white);
        $games->join($game, $black);

        // Revoking is a delete, and the seat goes with it.
        $white->delete();

        session([Visitors::SESSION_KEY => $black->id]);

        $this->get(route('chess.lobby'))
            ->assertOk()
            ->assertSee('bob is waiting for an opponent');
    }

    /**
     * Somebody who has never been here, reading what is on.
     *
     * The whole point of a second address for one list: `chess.lobby` is where
     * you go to play and asks you to arrive first, and this asks nothing. A
     * stranger who wanted to watch a game met a form asking them to name their
     * own server, which is a toll nobody pays to look at a chessboard.
     */
    public function test_a_stranger_can_read_what_games_are_on(): void
    {
        $games = $this->games();
        $game = $games->open($this->player('alice'));

        $games->join($game, $this->player('bob'));

        $this->get(route('chess.watch'))
            ->assertOk()
            ->assertSee(substr($game->key, -6));
    }

    /**
     * And is offered the way in rather than a button that does nothing.
     *
     * Starting a game refuses without a visitor, so on a page anybody may read
     * it had to stop being offered to the people it would refuse.
     */
    public function test_a_stranger_is_offered_the_way_in_rather_than_a_dead_button(): void
    {
        $this->get(route('chess.watch'))
            ->assertOk()
            ->assertSee('Sign in to play')
            ->assertDontSee('Start a game');
    }

    /**
     * A free chair is not an invitation to somebody the venue cannot name.
     *
     * A game with one player read "Play" to every stranger on the public list,
     * on a button wired to an action that refuses without a visitor — so it
     * promised a game it could not give and then did nothing when pressed. It
     * offers what it can actually deliver, and links to the table to deliver
     * it.
     */
    public function test_a_stranger_is_offered_a_look_rather_than_a_game(): void
    {
        $game = $this->games()->open($this->player('alice'));

        $this->get(route('chess.watch'))
            ->assertOk()
            ->assertSee(route('chess.table', $game->key), escape: false)
            ->assertDontSee('Play');
    }

    /**
     * A watcher is told what is happening, not asked to take a chair.
     *
     * The header swapped the whole status line for "Sit to play" for anybody
     * not seated. That was fine when only players had a live board and nobody
     * else had anything to be told — now that a passer-by watches, "not
     * seated" is the ordinary state of somebody following a game, and they
     * were shown an invitation instead of the game.
     */
    public function test_a_full_game_does_not_offer_a_watcher_a_chair(): void
    {
        $games = $this->games();
        $game = $games->open($this->player('alice'));

        $games->join($game, $this->player('bob'));

        $this->get(route('chess.table', $game->key))
            ->assertOk()
            ->assertDontSee('Sit to play');
    }

    /**
     * And still offers one while there is one, because there is.
     */
    public function test_a_game_with_a_free_chair_still_offers_it(): void
    {
        $game = $this->games()->open($this->player('alice'));

        $this->get(route('chess.table', $game->key))
            ->assertOk()
            ->assertSee('Sit to play');
    }

    /**
     * The way out of a game leads somewhere a stranger can actually go.
     */
    public function test_the_way_back_from_a_game_does_not_face_a_stranger_with_a_door(): void
    {
        $game = $this->games()->open($this->player('alice'));

        $this->get(route('chess.table', $game->key))
            ->assertOk()
            ->assertSee(route('chess.watch'), escape: false)
            ->assertDontSee(route('chess.lobby').'"', escape: false);
    }

    /**
     * Handing somebody the table across a room rather than across a network.
     *
     * Drawn on this side, so it is in the page rather than fetched.
     */
    public function test_a_game_can_be_handed_over_as_a_code(): void
    {
        $game = $this->games()->open($this->player('alice'));

        $this->get(route('chess.table', $game->key))
            ->assertOk()
            ->assertSee('Scan to open this game')
            ->assertSee('<svg', escape: false);
    }

    /**
     * The two things a laptop has no share sheet for.
     *
     * On a device with one, the button is the share and there is no menu at
     * all — pressing a button to be offered a chooser is being asked to choose
     * to choose.
     */
    public function test_a_laptop_is_offered_the_link_and_the_code(): void
    {
        $game = $this->games()->open($this->player('alice'));

        $this->get(route('chess.table', $game->key))
            ->assertOk()
            ->assertSee('Copy link')
            ->assertSee('QR code')
            ->assertSee('!disconnected && canShare', escape: false)
            ->assertSee('!disconnected && !canShare', escape: false);
    }

    /**
     * The one button offers something instead of narrating.
     *
     * "Waiting for white" sat where the only action goes and told somebody
     * what the board in front of them was already showing. A game under way
     * now offers the way to hand it to somebody.
     *
     * Asserted on the binding rather than the words: the line was drawn by
     * Alpine from room state, so its text was never in what the server sent
     * and a test looking for it would have passed all along.
     */
    public function test_the_action_spot_offers_something_rather_than_narrating(): void
    {
        $games = $this->games();
        $game = $games->open($this->player('alice'));

        $games->join($game, $this->player('bob'));

        $this->get(route('chess.table', $game->key))
            ->assertOk()
            ->assertSee('Share')
            ->assertDontSee('x-text="status"', escape: false);
    }

    /**
     * Watching is not playing. The list is readable; the door is still there.
     */
    public function test_the_lobby_itself_still_asks_somebody_to_arrive(): void
    {
        $this->get(route('chess.lobby'))->assertRedirect(route('venue.connect'));
    }

    /**
     * A game other people can watch is a better thing than a game they cannot,
     * so a third arrival joins the audience rather than being turned away.
     */
    public function test_a_third_person_watches_rather_than_being_turned_away(): void
    {
        $games = $this->games();
        $game = $games->open($this->player('alice'));

        $games->join($game, $this->player('bob'));

        $this->assertSame('', $games->join($game, $this->player('carol'))->seat);
    }

    /**
     * The end of the whole exercise: each player holds their own record of the
     * game, on the server they chose, signed by a venue that may not outlive it.
     */
    public function test_a_finished_game_reaches_both_players_own_stores(): void
    {
        $written = [];

        Http::fake(function ($request) use (&$written) {
            $written[] = $request->url();

            return Http::response(['uri' => 'at://did:web:somebody/'.ChessExperience::COLLECTION.'/3abc', 'cid' => 'bafy'], 201);
        });

        $games = $this->games();
        $game = $games->open($this->player('alice'));
        $games->join($game, $this->player('bob'));

        $records = $games->settle($game, $this->finished());

        $this->assertSame(['white', 'black'], array_keys($records));

        // Two servers written to, because the players do not live together.
        $this->assertCount(2, $written);
        $this->assertNotSame($written[0], $written[1]);

        $this->assertSame(Gathering::CONCLUDED, $game->refresh()->status);
    }

    /**
     * Written from each player's own point of view, because it is their record
     * rather than a row in somebody's database.
     */
    public function test_each_player_is_told_what_happened_to_them(): void
    {
        $sent = [];

        Http::fake(function ($request) use (&$sent) {
            $sent[] = $request->data();

            return Http::response(['uri' => 'at://x/y/z', 'cid' => 'bafy'], 201);
        });

        $games = $this->games();
        $game = $games->open($this->player('alice'));
        $games->join($game, $this->player('bob'));

        $games->settle($game, $this->finished());

        $claims = array_map(
            fn (array $body): array => json_decode(
                (string) base64_decode(strtr(explode('.', $body['record']['attestation'])[1], '-_', '+/'), true),
                true,
            ),
            $sent,
        );

        $this->assertSame(['win', 'loss'], array_column($claims, 'result'));
        $this->assertSame(['white', 'black'], array_column($claims, 'seat'));

        // And each names the other, so a record is a game rather than a score.
        $this->assertSame('did:web:bob.home.test', $claims[0]['opponent']);
        $this->assertSame('did:web:alice.home.test', $claims[1]['opponent']);
    }

    public function test_a_draw_is_a_draw_for_both_of_them(): void
    {
        $sent = [];

        Http::fake(function ($request) use (&$sent) {
            $sent[] = $request->data();

            return Http::response(['uri' => 'at://x/y/z', 'cid' => 'bafy'], 201);
        });

        $games = $this->games();
        $game = $games->open($this->player('alice'));
        $games->join($game, $this->player('bob'));

        $games->settle($game, $this->finished(['outcome' => 'stalemate', 'winner' => '']));

        foreach ($sent as $body) {
            $claims = json_decode(
                (string) base64_decode(strtr(explode('.', $body['record']['attestation'])[1], '-_', '+/'), true),
                true,
            );

            $this->assertSame('draw', $claims['result']);
            $this->assertSame('stalemate', $claims['outcome']);
        }
    }

    /**
     * Somebody withdrawing permission is an ordinary answer, and their opponent
     * should still end up with their record.
     */
    public function test_one_server_refusing_does_not_cost_the_other_player_theirs(): void
    {
        Http::fake(function ($request) {
            return str_contains($request->url(), 'alice')
                ? Http::response(['error' => 'invalid_token'], 401)
                : Http::response(['uri' => 'at://x/y/z', 'cid' => 'bafy'], 201);
        });

        $games = $this->games();
        $game = $games->open($this->player('alice'));
        $games->join($game, $this->player('bob'));

        $records = $games->settle($game, $this->finished());

        $this->assertSame(['black'], array_keys($records));
    }

    /**
     * The audience did not play, so there is nothing to say about them.
     */
    public function test_watchers_get_no_record(): void
    {
        Http::fake(fn () => Http::response(['uri' => 'at://x/y/z', 'cid' => 'bafy'], 201));

        $games = $this->games();
        $game = $games->open($this->player('alice'));
        $games->join($game, $this->player('bob'));
        $games->join($game, $this->player('carol'));

        $this->assertCount(2, $games->settle($game, $this->finished()));
    }

    /**
     * The lobby and the table are places people can talk.
     *
     * All this experience does is say so — the conversation itself lives in
     * the venue's badge, along with the party and the cameras. So what is
     * asserted here is that the declaration is made, and the venue's own
     * tests cover what is done with it.
     */
    public function test_the_lobby_is_somewhere_people_can_talk(): void
    {
        session([Visitors::SESSION_KEY => $this->player('alice')->id]);

        $this->get(route('chess.lobby'))
            ->assertOk()
            ->assertSee('data-streetmesh-space', escape: false)
            ->assertSee(ChessExperience::COLLECTION.'/lobby', escape: false);
    }

    public function test_a_table_is_somewhere_people_can_talk(): void
    {
        $alice = $this->player('alice');
        $game = $this->games()->open($alice);

        session([Visitors::SESSION_KEY => $alice->id]);

        $this->get(route('chess.table', $game->key))
            ->assertOk()
            ->assertSee('data-streetmesh-space', escape: false)
            ->assertSee($game->room(), escape: false);
    }
}
