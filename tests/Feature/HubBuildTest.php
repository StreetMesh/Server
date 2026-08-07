<?php

namespace Tests\Feature;

use StreetMesh\Venue\Experiences\Experiences;
use StreetMesh\Venue\Hub\Build;
use Tests\TestCase;

/**
 * The hub in this repository is the hub this server builds.
 *
 * `hub-build/` is generated and committed, which is not a preference: the
 * platform that runs a hub has Node and nothing else — no PHP, no Composer, no
 * submodules — while the list of installed experiences lives in a PHP registry.
 * Git is the only thing that reaches both sides.
 *
 * That arrangement has one failure mode. Somebody changes a room, does not run
 * the hub, and commits — and what deploys is the previous hub, silently, while
 * the code they wrote sits in the repository looking deployed.
 *
 * `./hub-serve` rebuilds before it starts, so anybody who ran the thing they
 * changed is already fine. This is for anybody who did not. A habit is not a
 * mechanism; a failing test is.
 */
final class HubBuildTest extends TestCase
{
    public function test_the_committed_hub_is_the_hub_this_server_builds(): void
    {
        $committed = base_path('hub-build/build.json');

        $this->assertFileExists(
            $committed,
            'hub-build/ is missing. Run `php artisan hub:build` and commit it.',
        );

        /*
         * Into somewhere else, because a test that repaired the thing it was
         * checking would pass and leave the repository changed underneath
         * whoever ran it.
         */
        $into = sys_get_temp_dir().'/hub-build-check-'.bin2hex(random_bytes(6));

        try {
            $built = (new Build(
                app(Experiences::class),
                base_path('hub'),
                $into,
            ))->run();
        } finally {
            if (is_dir($into)) {
                exec('rm -rf '.escapeshellarg($into));
            }
        }

        /** @var array{build: string} $carried */
        $carried = json_decode((string) file_get_contents($committed), true);

        $this->assertSame(
            $built['fingerprint'],
            $carried['build'] ?? null,
            "The hub in this repository is not the hub this server builds.\n"
            ."Run `php artisan hub:build` and commit the result — otherwise a deploy\n"
            .'ships the previous hub while this one sits here looking deployed.',
        );
    }
}
