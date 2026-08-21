<?php

namespace StreetMesh\Domicile\Tests;

use Flux\FluxServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use StreetMesh\Domicile\DomicileServiceProvider;
use StreetMesh\Protocol\Laravel\ProtocolServiceProvider;
use StreetMesh\Protocol\Network;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        // Livewire and Flux are listed because this package ships components
        // written in them, and testbench boots only what it is told about.
        return [
            LivewireServiceProvider::class,
            FluxServiceProvider::class,
            ProtocolServiceProvider::class,
            DomicileServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {

        /*
         * Nothing here talks to the outside world.
         *
         * A default rather than something each test opts into, because the
         * tests that reach the network by accident are exactly the ones that
         * did not think they would. A test wanting particular answers binds its
         * own.
         */
        $app->instance(Network::class, new OfflineNetwork);
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('streetmesh.host', 'home.test');

        /*
         * A stand-in for the host's chrome.
         *
         * This package ships screens written against the Livewire starter kit's
         * layout, which is the opinion the project settled on — so it cannot
         * render one of its own screens without a host. Pointing the `layouts`
         * namespace at a stub is what Livewire itself does with the real one on
         * boot, so this stands in the same way rather than a different way.
         */
        $app['config']->set('livewire.component_namespaces.layouts', __DIR__.'/fixtures/views/layouts');

        /*
         * And the same for the settings chrome. The avatar screen is a
         * settings screen, so it wears `<x-pages::settings.layout>` and
         * `partials.settings-heading` — both the application's, both stubbed
         * here for the same reason the layout above is.
         */
        /*
         * Through Livewire's own config rather than by hand: it turns each of
         * these into a view namespace *and* an anonymous component path, and
         * only the second makes `<x-pages::…>` resolve. Registering the view
         * namespace alone looks right and fails with "unable to locate a class
         * or view for component".
         */
        $app['config']->set('livewire.component_namespaces.pages', __DIR__.'/fixtures/views/pages');

        /* The heading is an ordinary include, so it wants an ordinary path. */
        $app['config']->set('view.paths', [
            ...(array) $app['config']->get('view.paths', []),
            __DIR__.'/fixtures/views',
        ]);
    }

    /**
     * A stand-in for the host's front door.
     *
     * This package has always assumed the application provides one —
     * `DomicileCapability::frontAction` sends people to a route named `login`
     * and nothing here defines it. Screens behind `auth` make that assumption
     * load-bearing, so the stub is here rather than in any one test.
     */
    protected function defineRoutes($router): void
    {
        $router->get('login', fn () => 'the door')->name('login');
    }

    protected function defineDatabaseMigrations(): void
    {
        // A domicile joins an account to an address, so these tests need both
        // halves: the framework's users and the protocol's identities.
        $this->loadLaravelMigrations();

        $this->loadMigrationsFrom(__DIR__.'/../vendor/streetmesh/protocol-laravel/database/migrations');

        // And this package's own, named the same way rather than left to the
        // provider — so what a test runs against is one list in one place.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
