<?php

namespace Tests\Feature;

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

        /*
         * Through the command rather than the class behind it.
         *
         * The library moved into the package, so where it lives is now the
         * command's answer to give. A test that reconstructed the path would be
         * a second opinion about it — and the first thing that would happen is
         * the two drifting apart, with this passing while a real build looked
         * somewhere else.
         */
        try {
            $this->artisan('hub:build', ['--into' => $into])->assertSuccessful();

            /** @var array{build: string} $built */
            $built = json_decode((string) file_get_contents($into.'/build.json'), true);
        } finally {
            if (is_dir($into)) {
                exec('rm -rf '.escapeshellarg($into));
            }
        }

        /** @var array{build: string} $carried */
        $carried = json_decode((string) file_get_contents($committed), true);

        $this->assertSame(
            $built['build'] ?? null,
            $carried['build'] ?? null,
            "The hub in this repository is not the hub this server builds.\n"
            ."Run `php artisan hub:build` and commit the result — otherwise a deploy\n"
            .'ships the previous hub while this one sits here looking deployed.',
        );
    }
}
