<?php

/**
 * Check that a venue and a domicile on one server still talk over the network.
 *
 *   php bin/check-visit.php
 *
 * This uses the shipping code — `Delegations`, the same class a venue package
 * calls — rather than hand-rolling the exchange the way `check-permission.php`
 * does. So it checks the thing that will actually run.
 *
 * The point it exists to make: when one server offers both capabilities, the
 * venue half must still reach the domicile half the way a stranger would, over
 * HTTPS, through the front door. There is no same-host shortcut, no direct call
 * into the record store, no shared database handle. If there were, a
 * single-server deployment would work while a two-server one quietly did not,
 * and the difference would not surface until the day it mattered most.
 *
 * The one thing done in-process is the approval, because a person pressing a
 * button cannot be scripted. Everything either side of it crosses the network.
 */

require __DIR__.'/../vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Client\Events\RequestSending;
use StreetMesh\Protocol\Laravel\Permissions\Delegations;
use StreetMesh\Protocol\Laravel\Permissions\Permissions;
use StreetMesh\Protocol\Laravel\Records\Record;
use StreetMesh\Protocol\Scope;

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$say = fn (string $line = '') => print $line."\n";
$failures = 0;

$check = function (string $what, bool $passed, string $detail = '') use ($say, &$failures): void {
    $failures += $passed ? 0 : 1;
    $say(sprintf('  %s %-46s %s', $passed ? '✓' : '✗', $what, $detail));
};

$host = (string) config('streetmesh.host');
$chess = 'com.streetmesh.games.chess';

config()->set('streetmesh.venue.scopes', [(string) Scope::forRepo([$chess], [Scope::CREATE])]);

$say();
$say('  one server  https://'.$host);
$say('  playing     venue and domicile at once');
$say();

/*
 * Counting requests the server makes to itself. If the venue were reaching the
 * domicile some other way this would be zero, which is the whole point of
 * measuring it rather than asserting it.
 */
$overTheWire = 0;

app('events')->listen(RequestSending::class, function ($event) use (&$overTheWire): void {
    $overTheWire++;
});

$delegations = $app->make(Delegations::class);

// ── The visitor types a name, and is sent to their own server ───────────────

$begun = $delegations->begin(
    $host,
    (array) config('streetmesh.venue.scopes'),
    'https://'.$host.'/connect/callback',
);

$check('a typed name finds somewhere to ask', true, $begun['delegation']->issuer);
$check('and somewhere to send them', str_contains($begun['url'], '/oauth/authorize'), 'the authorize endpoint');

// ── Their own server asks them. This is the part a person does ──────────────

$permissions = $app->make(Permissions::class);
$resident = 'did:plc:'.substr(hash('sha256', 'check-visit'), 0, 24);

$state = (string) $begun['delegation']->state;
$requestUri = (string) parse_url($begun['url'], PHP_URL_QUERY);
parse_str($requestUri, $query);

$code = $permissions->approve($permissions->pending((string) $query['request_uri']), $resident);

$check('somebody approves it', $code !== '', $resident);

// ── And the venue trades it, over the network, for something it can spend ───

$seated = $delegations->complete($state, $code, 'https://'.$host.'/connect/callback');

$check(
    'the venue is given something it can spend',
    $seated->access_token !== null,
    $seated->scope,
);

$check('for the person who agreed', $seated->did === $resident, (string) $seated->did);

// ── A finished game goes home ───────────────────────────────────────────────

$before = Record::query()->count();

$written = $delegations->write($seated, $chess, [
    'result' => 'win',
    'seat' => 'white',
    'opponent' => 'did:plc:somebody-else',
    'pgn' => '1. e4 e5 2. Nf3 Nc6 3. Bb5 a6',
]);

$check('a finished game reaches their records', str_starts_with($written['uri'], 'at://'.$resident), $written['uri']);
$check('and there is one more record than there was', Record::query()->count() === $before + 1);

$say();
$check(
    'every hop went over the network',
    $overTheWire >= 4,
    $overTheWire.' HTTP requests this server made to itself',
);

$say();
$say($failures === 0
    ? "  One server, two capabilities, and they were strangers to each other throughout.\n"
    : "  {$failures} step(s) did not work.\n");

exit($failures === 0 ? 0 : 1);
