<?php

/**
 * Mint one real ticket, for the realtime half to check.
 *
 *   php bin/mint-ticket.php | node --experimental-strip-types hub/bin/check-ticket.ts
 *
 * Prints exactly what a venue would hand a browser on its way to a room, using
 * this server's real identity and the key it actually publishes. Everything the
 * other side needs to check it — the DID document — it fetches itself.
 *
 * Writes nothing. A ticket is good for a minute and names one room.
 */

require __DIR__.'/../vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use StreetMesh\Protocol\Laravel\Permissions\Delegation;
use StreetMesh\Protocol\Laravel\Permissions\Tickets;
use StreetMesh\Protocol\P256;

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/*
 * A room of its own by default, or one already open when named — so two
 * tickets can be minted for one table, which is what a second player needs.
 */
$room = $argv[1] ?? 'com.streetmesh.games.chess/'.bin2hex(random_bytes(4));
$seat = $argv[2] ?? 'white';

/*
 * Not saved. This stands in for somebody already seated, which the realtime
 * half has no way of knowing anything about — it is told, in a signature.
 *
 * Nameable, because two tickets for one table are usually two *people*: a
 * check that minted the same visitor twice found only that nobody may take
 * two seats, which is true and not what it was asking.
 */
$who = $argv[3] ?? (isset($argv[1]) ? 'bob' : 'alice');

$visitor = new Delegation([
    'did' => 'did:web:'.$who.'.home.test',
    'handle' => $who.'.home.test',
    'issuer' => 'https://home.test',
    'dpop_key' => Delegation::store(P256::generate()),
    'scope' => 'atproto',
]);

$tickets = $app->make(Tickets::class);

/*
 * A second ticket, properly signed and already expired.
 *
 * Editing a good ticket's expiry would break its signature, and the other side
 * would refuse it for that instead — which reads as a passing test while the
 * expiry check never runs. The only way to test expiry is to sign one that has
 * genuinely run out.
 */
Carbon::setTestNow(now()->subHour());
$expired = $tickets->mint($visitor, $room, seat: $seat);
Carbon::setTestNow();

echo json_encode([
    'ticket' => $tickets->mint($visitor, $room, seat: $seat),
    'expired' => $expired,
    'room' => $room,
    'expect' => [
        'sub' => $visitor->did,
        'name' => $visitor->handle,
        'seat' => $seat,
    ],
], JSON_UNESCAPED_SLASHES), "\n";
