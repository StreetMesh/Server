<?php

/**
 * Check that this server grants permission to a venue over real HTTP.
 *
 *   php bin/check-permission.php
 *
 * Every other check in this project points outward, at somebody else's server.
 * This one points at us: it plays the venue against our own domicile, over the
 * network, through the actual web server rather than the test harness.
 *
 * What that exercises which unit tests cannot: TLS, routing, the middleware
 * stack, a nonce surviving a round trip in a real header, and — the part most
 * likely to be wrong — this server fetching a client metadata document and a
 * key set over HTTP while it is itself in the middle of answering a request.
 *
 * It uses this server as both halves, which is a fair test of the mechanism and
 * not of the federation: the venue is a stranger to the domicile in every way
 * that matters here, because the domicile stores nothing about it and looks
 * everything up as the request arrives. Two separate hosts is what `Home` and
 * `Games` are for.
 *
 * Writes one row per run, which is rather the point — a permission somebody
 * genuinely asked for.
 */

require __DIR__.'/../vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel;
use StreetMesh\Protocol\AuthorizationRequest;
use StreetMesh\Protocol\ClientAssertion;
use StreetMesh\Protocol\Dpop;
use StreetMesh\Protocol\Laravel\Identity\Identities;
use StreetMesh\Protocol\Laravel\Permissions\Permissions;
use StreetMesh\Protocol\P256;
use StreetMesh\Protocol\Pkce;
use StreetMesh\Protocol\Scope;

/*
 * Booted after the imports, not before. An alias applies only from the line it
 * appears on, so bootstrapping above this block resolves `Kernel::class` to the
 * bare string "Kernel" and fails inside the container — which is what happened
 * when the formatter moved the imports down.
 */
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$issuer = rtrim((string) config('app.url'), '/');
$par = $issuer.'/oauth/par';
$client = $issuer.'/client-metadata.json';

$venue = $app->make(Identities::class)->forServer()->key();

$say = fn (string $line = '') => print $line."\n";
$failures = 0;

$check = function (string $what, bool $passed, string $detail = '') use ($say, &$failures): void {
    $failures += $passed ? 0 : 1;
    $say(sprintf('  %s %-46s %s', $passed ? '✓' : '✗', $what, $detail));
};

/**
 * One request, with a proof, and no cleverness about nonces.
 *
 * @param  array<string, string>  $form
 * @return array{0: int, 1: array<string, string>, 2: mixed}
 */
$once = function (string $url, array $form, string $proof): array {
    $handle = curl_init($url);
    $headers = [];

    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($form),
        CURLOPT_HTTPHEADER => [
            'DPoP: '.$proof,
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,

        /*
         * A local certificate from a certificate authority that exists on this
         * machine only. Nothing here is a claim about the wider internet.
         */
        CURLOPT_SSL_VERIFYPEER => false,

        CURLOPT_HEADERFUNCTION => function ($ignored, string $line) use (&$headers): int {
            $parts = explode(':', $line, 2);

            if (count($parts) === 2) {
                $headers[trim($parts[0])] = trim($parts[1]);
            }

            return strlen($line);
        },
    ]);

    $body = (string) curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);

    curl_close($handle);

    return [$status, $headers, json_decode($body, true) ?? $body];
};

/**
 * The same, retrying once when the server says the nonce has moved.
 *
 * This is not a convenience for the script — it is what every client of this
 * profile has to do. Nonces rotate every few minutes, so being told to use a
 * new one is an ordinary event in the middle of a working conversation, and a
 * client that treats it as a failure works perfectly until the first rotation
 * and then breaks at a boundary it cannot see. Writing this check without the
 * retry is exactly how that was found.
 *
 * @param  array<string, string>  $form
 * @return array{0: int, 1: array<string, string>, 2: mixed}
 */
$ask = function (string $url, array $form, P256 $with) use ($once, &$nonce): array {
    $answer = $once($url, $form, Dpop::proof($with, 'POST', $url, nonce: $nonce));

    $offered = Dpop::nonceFrom($answer[1]);

    if ($offered !== null) {
        $nonce = $offered;
    }

    if (($answer[2]['error'] ?? null) !== 'use_dpop_nonce') {
        return $answer;
    }

    return $once($url, $form, Dpop::proof($with, 'POST', $url, nonce: $nonce));
};

$say();
$say('  venue     '.$client);
$say('  domicile  '.$issuer);
$say();

$key = P256::generate();
$pkce = Pkce::generate();

/*
 * `action=create` and nothing more, which is all a venue writing a finished
 * game ever needs — and what a resident reading the request would be told.
 */
$scope = 'atproto '.Scope::forRepo(['com.streetmesh.games.chess'], [Scope::CREATE]);

$fields = AuthorizationRequest::pushed(
    clientId: $client,
    redirectUri: $issuer.'/visit/callback',
    state: bin2hex(random_bytes(8)),
    pkce: $pkce,
    assertion: ClientAssertion::for($client, $issuer, $venue),
    scopes: explode(' ', $scope),
    loginHint: (string) config('streetmesh.host'),
);

$nonce = null;

[$status, $headers, $body] = $once($par, $fields, Dpop::proof($key, 'POST', $par));
$nonce = Dpop::nonceFrom($headers);

$check(
    'a request without a nonce is turned away',
    $status === 400 && ($body['error'] ?? null) === 'use_dpop_nonce',
    'HTTP '.$status,
);

$check('and is handed one to use', $nonce !== null, (string) $nonce);

/*
 * A fresh assertion, because the first one has now been spent. Reusing it must
 * fail, and the last check below is what confirms it does.
 */
$fields['client_assertion'] = ClientAssertion::for($client, $issuer, $venue);

[$status, $headers, $body] = $ask($par, $fields, $key);

$check(
    'the request is accepted',
    $status === 201 && isset($body['request_uri']),
    $status === 201
        ? (string) $body['request_uri']
        : 'HTTP '.$status.' '.($body['error_description'] ?? ($body['error'] ?? '')),
);

/*
 * Getting this far required the domicile to fetch the venue's own documents
 * mid-request and check a signature against them. Named separately because it
 * is the part no unit test reaches.
 */
$check('after fetching our documents over the network', $status === 201, 'client metadata, then the key set');

$replayed = $ask($par, $fields, $key);

$check(
    'and a spent assertion is refused',
    $replayed[0] !== 201,
    (string) ($replayed[2]['error_description'] ?? $replayed[2]['error'] ?? ''),
);

if ($failures > 0) {
    $say("\n  {$failures} step(s) did not work.\n");

    exit(1);
}

/*
 * The browser's part, done here without a browser.
 *
 * A person signs in and presses a button; there is nobody to do that from a
 * script, so the approval is made directly against the same method the consent
 * screen calls. Everything before and after it is the real thing over HTTP —
 * this is the one seam, and it is where a person genuinely belongs.
 */
$say();

$permissions = $app->make(Permissions::class);
$resident = 'did:plc:'.substr(hash('sha256', 'check-permission'), 0, 24);

$code = $permissions->approve(
    $permissions->pending((string) $body['request_uri']),
    $resident,
);

$check('somebody approves it', $code !== '', $resident);

// ── And now the exchange resumes over the wire ──────────────────────────────

$token = $issuer.'/oauth/token';

[$status, $headers, $body] = $ask(
    $token,
    AuthorizationRequest::redeem(
        clientId: $client,
        redirectUri: $issuer.'/visit/callback',
        code: $code,
        pkce: $pkce,
        assertion: ClientAssertion::for($client, $issuer, $venue),
    ),
    $key,
);

$granted = is_array($body) ? $body : [];

$check(
    'the code is traded for a token',
    $status === 200 && isset($granted['access_token']),
    $status === 200
        ? $granted['token_type'].', '.$granted['expires_in'].'s, '.$granted['scope']
        : 'HTTP '.$status.' '.($granted['error_description'] ?? ($granted['error'] ?? '')),
);

if ($status !== 200) {
    $say("\n  The exchange stopped before a record could be written.\n");

    exit(1);
}

$access = (string) $granted['access_token'];
$write = $issuer.'/xrpc/com.atproto.repo.createRecord';

/**
 * @param  array<string, mixed>  $record
 * @return array{0: int, 2: mixed}
 */
$post_record = function (string $token, P256 $with) use ($write): array {
    $handle = curl_init($write);
    $headers = [];

    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => (string) json_encode([
            'collection' => 'com.streetmesh.games.chess',
            'record' => ['result' => 'win', 'seat' => 'white', 'pgn' => ''],
        ]),
        CURLOPT_HTTPHEADER => [
            'Authorization: DPoP '.$token,
            'DPoP: '.Dpop::proof($with, 'POST', $write, accessToken: $token),
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $body = (string) curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);

    curl_close($handle);

    return [$status, json_decode($body, true) ?? $body];
};

[$status, $written] = $post_record($access, $key);

$check(
    'a record is written into their store',
    $status === 201 && isset($written['uri']),
    $status === 201 ? (string) $written['uri'] : 'HTTP '.$status.' '.($written['message'] ?? ''),
);

$check(
    'and it belongs to them rather than to the venue',
    $status === 201 && str_starts_with((string) ($written['uri'] ?? ''), 'at://'.$resident),
    $resident,
);

// The one that makes the token worth binding to a key at all.
[$stolen] = $post_record($access, P256::generate());

$check('a copied token is worthless to another key', $stolen === 401, 'HTTP '.$stolen);

$say();
$say($failures === 0
    ? "  A venue this server had never heard of wrote a game into somebody's own records.\n"
    : "  {$failures} step(s) did not work.\n");

exit($failures === 0 ? 0 : 1);
