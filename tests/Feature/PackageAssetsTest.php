<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * What a package says it puts on the page is there.
 *
 * A package declares its browser assets under `extra.streetmesh` in its own
 * composer.json, and `vite/streetmesh.js` resolves those declarations when Vite
 * starts. Declaring a path that does not exist fails the build — but only for
 * somebody who runs the build, and the PHP half of a change is live long before
 * anybody does.
 *
 * That is the gap this closes. Move a component, rename a views directory, and
 * every PHP test still passes while the page quietly loses a component or every
 * class in a package's markup. The suite that runs on every push should be the
 * thing that notices, rather than an unstyled page somebody sees later.
 */
final class PackageAssetsTest extends TestCase
{
    public function test_every_declared_asset_path_exists(): void
    {
        $declared = 0;

        foreach ($this->packages() as $name => $path) {
            $streetmesh = $this->declaration($path);

            if ($streetmesh === null) {
                continue;
            }

            foreach (['components', 'views', 'entries'] as $kind) {
                foreach ((array) ($streetmesh[$kind] ?? []) as $relative) {
                    $declared++;

                    $this->assertFileExists(
                        $path.'/'.$relative,
                        "{$name} declares \"{$relative}\" as extra.streetmesh.{$kind}, and it is not there.\n"
                        .'Vite would refuse to start; the page would lose whatever that file carries.',
                    );
                }
            }
        }

        $this->assertGreaterThan(
            0,
            $declared,
            'No package declared any assets, which means this test is checking nothing. '
            .'Either the declarations were removed or they are no longer being read from composer.json.',
        );
    }

    /**
     * Read from the package rather than from installed.json, for the reason
     * `vite/streetmesh.js` does: Composer copies `extra` in at install time, so
     * a package edited in place reads as it was installed rather than as it is.
     *
     * @return array<string, mixed>|null
     */
    private function declaration(string $path): ?array
    {
        if (! is_file($path.'/composer.json')) {
            return null;
        }

        /** @var array{extra?: array{streetmesh?: array<string, mixed>}} $composer */
        $composer = json_decode((string) file_get_contents($path.'/composer.json'), true);

        return $composer['extra']['streetmesh'] ?? null;
    }

    /**
     * Everything Composer has installed, by real path — symlinks followed, so
     * a package mounted from `packages/` reports where it actually is.
     *
     * @return array<string, string>
     */
    private function packages(): array
    {
        /** @var array{packages: array<int, array{name: string, install-path?: string}>} $installed */
        $installed = json_decode(
            (string) file_get_contents(base_path('vendor/composer/installed.json')),
            true,
        );

        $found = [];

        foreach ($installed['packages'] as $package) {
            $path = realpath(base_path('vendor/composer/'.($package['install-path'] ?? '')));

            if ($path !== false) {
                $found[$package['name']] = $path;
            }
        }

        return $found;
    }
}
