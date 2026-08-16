<?php

namespace StreetMesh\Chess\Http;

use Illuminate\Http\JsonResponse;
use StreetMesh\Chess\ChessExperience;
use StreetMesh\Venue\Gatherings\Gathering;
use StreetMesh\Venue\Gatherings\Results;
use StreetMesh\Venue\Gatherings\Settling;

/**
 * Getting a finished game into the players' own records.
 *
 * The whole point of the exercise, and the last thing to be wired up. A game
 * nobody keeps is an afternoon; a game each player ends up holding their own
 * signed record of is the thing this project exists to demonstrate.
 *
 * A browser asks for this, and asking is all it can do. What happened comes
 * from the hub, which decided it; whether it happened at all comes from the
 * hub too, because a game still being played answers nothing. So the worst a
 * visitor can achieve by calling this early, or often, or for somebody else's
 * table, is to make this server ask a question it already knows the answer to.
 */
final class SettleController
{
    public function __invoke(string $key, Results $results): JsonResponse
    {
        $game = Gathering::query()
            ->where('experience', ChessExperience::COLLECTION)
            ->keyed($key)
            ->first();

        if ($game === null) {
            return response()->json(['settled' => false, 'because' => 'no such game'], 404);
        }

        /*
         * Already done. Settling twice would write each player a second record
         * of the same game, and a repository is append-only — there would be no
         * taking it back.
         */
        if (! $game->isOpen()) {
            return response()->json(['settled' => true, 'already' => true]);
        }

        $result = $results->of($game);

        if ($result === null) {
            return response()->json(['settled' => false, 'because' => 'not over']);
        }

        /*
         * Handed to the queue rather than done here, and by the same route the
         * hub's announcement takes — writing a record means calling each
         * player's own server, and a browser should not be holding a request
         * open while this venue waits on somebody else's afternoon.
         *
         * One job per gathering however many messengers arrive.
         */
        Settling::dispatch($game, $result);

        return response()->json(['settled' => true, 'queued' => true]);
    }
}
