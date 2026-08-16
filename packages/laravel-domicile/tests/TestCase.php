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
    }

    protected function defineDatabaseMigrations(): void
    {
        // A domicile joins an account to an address, so these tests need both
        // halves: the framework's users and the protocol's identities.
        $this->loadLaravelMigrations();

        $this->loadMigrationsFrom(__DIR__.'/../vendor/streetmesh/protocol-laravel/database/migrations');
    }
}
