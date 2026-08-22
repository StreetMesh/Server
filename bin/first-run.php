<?php

/**
 * What kind of server is this?
 *
 *   php bin/first-run.php
 *
 * Run once, by `composer create-project`, before anything has been decided.
 * Everything it asks about is a thing that has no sensible default and no good
 * failure: a venue with no hub refuses to boot, a server with no host publishes
 * a document naming nothing, and a secret nobody generated is a secret.
 *
 * It writes to `.env` and stops. It does not migrate, install, or start
 * anything — those already have their own step, and a script that did several
 * unrelated things would be a script nobody could run twice.
 *
 * Safe to run again: every value it writes, it writes over. Safe to skip
 * entirely — `--no-interaction`, a CI runner, or any terminal that cannot ask a
 * question leaves the file as it was and says which values to set by hand.
 */

require __DIR__.'/../vendor/autoload.php';

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

$env = __DIR__.'/../.env';

if (! is_file($env)) {
    warning('There is no .env yet, so there is nothing to write to.');
    exit(0);
}

/*
 * A question nobody can answer is not a question.
 *
 * Composer runs its scripts with stdin attached to whatever ran Composer, which
 * on a CI runner is not a person. Prompting there hangs a build until it is
 * killed, and the fix everybody reaches for — a timeout — turns it into a
 * server configured by accident.
 */
if (in_array('--no-interaction', $argv, true) || ! stream_isatty(STDIN)) {
    note('Skipping the setup questions — nothing is here to answer them.');
    note('Set STREETMESH_HOST, and STREETMESH_DOMICILE or STREETMESH_VENUE, in .env yourself.');

    exit(0);
}

/**
 * Set a key in `.env`, replacing whatever was there.
 *
 * Matches a commented-out key as well as a live one, because the example file
 * carries several switches commented out with their defaults beside them — and
 * answering a question should uncomment the answer rather than leave a live
 * value below a commented one saying something else.
 */
$put = function (string $key, string $value) use ($env): void {
    $contents = (string) file_get_contents($env);
    $line = $key.'='.$value;

    $contents = preg_match('/^#?\s*'.preg_quote($key, '/').'=.*$/m', $contents) === 1
        ? (string) preg_replace('/^#?\s*'.preg_quote($key, '/').'=.*$/m', $line, $contents)
        : rtrim($contents, "\n")."\n".$line."\n";

    file_put_contents($env, $contents);
};

info('Two things a StreetMesh server can be, and it may be either or both.');

$kind = select(
    label: 'What is this server?',
    options: [
        'domicile' => 'A domicile — people live here, and their records are yours to keep',
        'venue' => 'A venue — people arrive from elsewhere and do things together',
        'both' => 'Both — one server, two halves',
    ],
    default: 'both',
);

$isDomicile = $kind === 'domicile' || $kind === 'both';
$isVenue = $kind === 'venue' || $kind === 'both';

/*
 * The name, first, because everything else is downstream of it. It is this
 * server's identifier under `did:web`, it is what every signature is checked
 * against, and it is read when the first identity is minted — so changing it
 * later renames nothing that already exists.
 */
$host = text(
    label: 'What name do strangers reach this server by?',
    placeholder: 'server.test',
    default: 'server.test',
    hint: 'The real one, not a local alias — it becomes this server\'s identifier.',
);

$put('STREETMESH_HOST', $host);
$put('STREETMESH_DOMICILE', $isDomicile ? 'true' : 'false');
$put('STREETMESH_VENUE', $isVenue ? 'true' : 'false');

if ($kind === 'both') {
    /*
     * One root, two halves that both have something to say at it. Neither can
     * decide, so the operator does.
     */
    $put('STREETMESH_FRONT_PAGE', select(
        label: 'Which half greets somebody at the front door?',
        options: ['domicile' => 'The domicile', 'venue' => 'The venue'],
        default: 'domicile',
    ));
}

if ($isVenue) {
    /*
     * A venue refuses to boot without both of these, and says so — see
     * `Readiness`. Asking now is the difference between a working server and a
     * RuntimeException on the first page load.
     */
    $put('STREETMESH_HUB', text(
        label: 'Where does this venue\'s hub run?',
        placeholder: 'wss://hub.test',
        default: 'wss://hub.test',
        hint: 'The Node half. `./hub-serve` runs one locally.',
    ));

    $put('STREETMESH_REALTIME_SECRET', bin2hex(random_bytes(32)));

    note('Generated STREETMESH_REALTIME_SECRET. The hub needs the same value wherever it runs.');
}

if ($isDomicile && confirm(
    label: 'Keep a PLC directory here, for development?',
    default: true,
    hint: 'Identifiers published to the real directory are permanent and global.',
)) {
    $put('STREETMESH_PLC_HOST', 'true');
    $put('STREETMESH_PLC_DIRECTORY', '${APP_URL}/plc');
}

info('Written to .env.');

note(match ($kind) {
    'domicile' => 'A domicile. Run `php artisan migrate`, then sign somebody up and give them an address.',
    'venue' => 'A venue. Run `php artisan migrate`, start `./hub-serve`, and add an experience.',
    'both' => 'Both halves. Run `php artisan migrate`, then `./hub-serve` for the venue side.',
});
