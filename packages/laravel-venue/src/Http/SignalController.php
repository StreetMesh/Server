<?php

namespace StreetMesh\Venue\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use StreetMesh\Venue\Media\Mailbox;
use StreetMesh\Venue\Parties\Parties;
use StreetMesh\Venue\Parties\Party;
use StreetMesh\Venue\Visitors;

/**
 * Carrying the few notes that let two browsers in a party find each other.
 *
 * Ordinary HTTP, deliberately. This is not gameplay and it is not hot: an offer,
 * an answer, a handful of addresses, and then it is over for good — after which
 * the audio and video go directly between the two browsers and nothing arrives
 * here at all.
 *
 * Being in the party is the whole of the access control, checked on both verbs.
 * A party is private by construction, so anybody who is not in one has no
 * business either reading what is waiting in it or leaving anything there.
 *
 * Polled rather than pushed, which is what it should stop being. The server this
 * runs on has Reverb configured, and the cost of asking grows with the number of
 * people in the party — see the note in `decisions/parties.md`.
 */
final class SignalController
{
    public function __construct(
        private readonly Parties $parties,
        private readonly Visitors $visitors,
        private readonly Mailbox $mailbox,
    ) {}

    /** Take everything waiting for this connection. */
    public function collect(Request $request, string $key): JsonResponse
    {
        $party = $this->partyFor($request, $key);

        if ($party === null) {
            return $this->refuse();
        }

        return response()->json([
            'signals' => $this->mailbox->drain($party->room(), (string) $request->string('as')),
        ]);
    }

    /**
     * Leave a note for somebody else in the party.
     *
     * Who it is from is taken from the request rather than from the session,
     * and that is not a hole: everybody who can reach this is in the same
     * private party, and what is being named is a browser tab rather than a
     * person. The identity that matters was settled at the door.
     */
    public function post(Request $request, string $key): JsonResponse
    {
        $party = $this->partyFor($request, $key);

        if ($party === null) {
            return $this->refuse();
        }

        $to = (string) $request->string('to');

        if ($to === '') {
            return response()->json(['error' => 'A note needs somebody to be for.'], 422);
        }

        /** @var array<string, mixed> $note */
        $note = $request->array('data');

        $this->mailbox->post(
            $party->room(),
            (string) $request->string('from'),
            $to,
            $note,
        );

        return response()->json(['left' => true]);
    }

    /**
     * The party this request is about, if the person making it is in it.
     *
     * One question rather than two, because every answer here is the same: a
     * party that does not exist and a party you are not in are indistinguishable
     * from outside, and telling them apart would say whether a given party
     * exists to somebody with no business knowing.
     */
    private function partyFor(Request $request, string $key): ?Party
    {
        $visitor = $this->visitors->current($request);

        if ($visitor === null) {
            return null;
        }

        $party = Party::query()->keyed($key)->whereNull('disbanded_at')->first();

        if ($party === null || $this->parties->memberOf($party, $visitor) === null) {
            return null;
        }

        return $party;
    }

    private function refuse(): JsonResponse
    {
        return response()->json(['error' => 'There is no party of yours by that name.'], 404);
    }
}
