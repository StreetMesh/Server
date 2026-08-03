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
use StreetMesh\Protocol\P256;
use StreetMesh\Protocol\Pkce;

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
 * @param  array<string, string>  $form
 * @return array{0: int, 1: array<string, string>, 2: mixed}
 */
$post = function (string $url, array $form, string $proof): array {
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

$say();
$say('  venue     '.$client);
$say('  domicile  '.$issuer);
$say();

$key = P256::generate();
$pkce = Pkce::generate();

$fields = AuthorizationRequest::pushed(
    clientId: $client,
    redirectUri: $issuer.'/visit/callback',
    state: bin2hex(random_bytes(8)),
    pkce: $pkce,
    assertion: ClientAssertion::for($client, $issuer, $venue),
    loginHint: (string) config('streetmesh.host'),
);

[$status, $headers, $body] = $post($par, $fields, Dpop::proof($key, 'POST', $par));
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

[$status, $headers, $body] = $post($par, $fields, Dpop::proof($key, 'POST', $par, nonce: $nonce));

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

$replayed = $post($par, $fields, Dpop::proof($key, 'POST', $par, nonce: $nonce));

$check(
    'and a spent assertion is refused',
    $replayed[0] !== 201,
    (string) ($replayed[2]['error_description'] ?? $replayed[2]['error'] ?? ''),
);

$say();
$say($failures === 0
    ? "  A venue this server had never heard of got as far as asking somebody.\n"
    : "  {$failures} step(s) did not work.\n");

exit($failures === 0 ? 0 : 1);
