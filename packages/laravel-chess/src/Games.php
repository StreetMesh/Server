<?php

namespace StreetMesh\Chess;

use StreetMesh\Protocol\Laravel\Permissions\Delegation;
use StreetMesh\Protocol\Laravel\Permissions\Delegations;
use StreetMesh\Venue\Gatherings\Gathering;
use StreetMesh\Venue\Gatherings\Gatherings;
use StreetMesh\Venue\Gatherings\Seat;
use Throwable;

/**
 * Opening a game, sitting down at one, and getting the result home.
 *
 * The venue half of chess. It knows nothing about how a knight moves — that is
 * the hub's business, and deliberately not duplicated here, because two
 * implementations of the rules is two chances to disagree about who won.
 *
 * What it knows is everything the rules do not: that a game exists, who is
 * playing it, and that when it ends each player should end up holding a record
 * of it on the server they chose to live on.
 */
final class Games
{
    public function __construct(
        private readonly Gatherings $gatherings,
        private readonly Delegations $delegations,
    ) {}

    /**
     * A new game, with whoever opened it already in a seat.
     */
    public function open(Delegation $host, string $seat = 'white'): Gathering
    {
        $game = $this->gatherings->open(ChessExperience::COLLECTION);

        $this->gatherings->seat($game, $host, $seat);

        return $game;
    }

    /**
     * Take the other chair, or a place in the audience if both are taken.
     *
     * Somebody arriving at a game already underway is watching rather than
     * being turned away, because a game other people can watch is a better
     * thing than a game they cannot.
     */
    public function join(Gathering $game, Delegation $visitor): Seat
    {
        // Somebody already at this table is returning to their own chair, not
        // taking a second one.
        $already = $this->gatherings->seatOf($game, $visitor);

        if ($already !== null) {
            return $this->gatherings->seat($game, $visitor, $already->seat);
        }

        foreach (['white', 'black'] as $seat) {
            if (! $this->taken($game, $seat)) {
                return $this->gatherings->seat($game, $visitor, $seat);
            }
        }

        return $this->gatherings->seat($game, $visitor);
    }

    /**
     * The game is over: write each player their own copy.
     *
     * Each record is signed by this venue and written into that player's own
     * store, which is the whole point of the exercise. They are separate
     * records rather than one shared one, because there is no shared place for
     * one to live — the players are on different servers, and after tonight
     * this venue may not exist.
     *
     * A player whose server refuses is skipped rather than failing the others.
     * Somebody having withdrawn permission is an ordinary answer, and the
     * opponent should still get their record.
     *
     * @param  array{outcome: string, winner: string, moves: array<int, string>, positions?: array<int, string>, fen: string}  $result
     * @return array<string, string> what was written, by seat
     */
    public function settle(Gathering $game, array $result): array
    {
        if ($game->isOpen()) {
            /*
             * What happened, kept by the venue as well as sent to each player's
             * own server. The hub has already forgotten by the time anybody
             * comes back to look, so without this a finished game is a board
             * this screen cannot draw.
             */
            $this->gatherings->conclude($game, [
                'outcome' => $result['outcome'],
                'winner' => $result['winner'],
                'moves' => array_values($result['moves']),

                // The position after each move, so a finished game can be
                // replayed by anybody without them having to know the rules.
                'positions' => array_values($result['positions'] ?? []),

                'fen' => $result['fen'],
            ]);
        }

        $written = [];

        foreach ($game->seats()->with('delegation')->get() as $seat) {
            if ($seat->seat === '') {
                // The audience gets no record. They did not play.
                continue;
            }

            try {
                $written[$seat->seat] = $this->delegations->write(
                    $seat->delegation,
                    ChessExperience::COLLECTION,
                    $this->claims($game, $seat, $result),
                )['uri'];
            } catch (Throwable $refused) {
                report($refused);
            }
        }

        return $written;
    }

    /**
     * What the venue is willing to say happened.
     *
     * Written from one player's point of view, because that is whose record it
     * is — "you won" rather than "white won", so it reads as a thing that
     * happened to them rather than a row in somebody's database.
     *
     * @param  array{outcome: string, winner: string, moves: array<int, string>, positions?: array<int, string>, fen: string}  $result
     * @return array<string, mixed>
     */
    private function claims(Gathering $game, Seat $seat, array $result): array
    {
        return [
            'type' => ChessExperience::COLLECTION,
            'venue' => (string) config('streetmesh.host'),
            'game' => $game->key,

            'seat' => $seat->seat,
            'opponent' => $this->opponent($game, $seat),

            'result' => match (true) {
                $result['winner'] === '' => 'draw',
                $result['winner'] === $seat->seat => 'win',
                default => 'loss',
            },

            /*
             * How it ended as well as who won, because "checkmate" and
             * "the other player left" are different stories about the same
             * result and only one of them is worth retelling.
             */
            'outcome' => $result['outcome'],

            'moves' => array_values($result['moves']),
            'position' => $result['fen'],

            'playedAt' => now()->toAtomString(),
        ];
    }

    private function opponent(Gathering $game, Seat $seat): string
    {
        $other = $game->seats()
            ->with('delegation')
            ->where('id', '!=', $seat->id)
            ->whereIn('seat', ['white', 'black'])
            ->first();

        return $other?->delegation->did ?? '';
    }

    private function taken(Gathering $game, string $seat): bool
    {
        return $game->seats()->where('seat', $seat)->whereNull('left_at')->exists();
    }
}
