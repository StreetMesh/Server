<?php

namespace StreetMesh\Chess\Http;

use Illuminate\Http\RedirectResponse;
use StreetMesh\Chess\ChessExperience;
use StreetMesh\Chess\Games;
use StreetMesh\Venue\Gatherings\Gathering;
use StreetMesh\Venue\Visitors;

/**
 * Taking a chair at a table somebody was invited to.
 *
 * Behind the door, which is the whole point of it being a separate address: the
 * table itself is readable by anybody, and this is the first thing that asks
 * who they are. Somebody arriving here without a delegation is sent to connect
 * and returned afterwards, because the middleware remembers where they were
 * going.
 *
 * Which chair they get is the venue's answer, not the button's. Both taken and
 * they are watching — a game other people can watch is a better thing than a
 * game they cannot.
 */
final class SitController
{
    public function __invoke(string $key, Games $games, Visitors $visitors): RedirectResponse
    {
        $game = Gathering::query()
            ->where('experience', ChessExperience::COLLECTION)
            ->keyed($key)
            ->first();

        $visitor = $visitors->current(request());

        if ($game !== null && $visitor !== null && $game->isOpen()) {
            $games->join($game, $visitor);
        }

        return redirect()->route('chess.table', $key);
    }
}
