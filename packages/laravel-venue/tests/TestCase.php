<?php

namespace StreetMesh\Venue\Tests;

use Flux\FluxServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use StreetMesh\Protocol\Laravel\ProtocolServiceProvider;
use StreetMesh\Protocol\Network;
use StreetMesh\Venue\VenueServiceProvider;

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
            VenueServiceProvider::class,
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

        /*
         * The same referential integrity the real database has.
         *
         * SQLite leaves foreign keys off unless asked, so a delete that
         * cascades in production quietly leaves the child rows behind here.
         * A seat outliving the permission it belongs to is not a shape any
         * server ever sees, and testing against it hid a crash in the lobby
         * for a state that only arises when somebody revokes.
         */
        $app['config']->set('database.connections.testing.foreign_key_constraints', true);
        $app['config']->set('streetmesh.host', 'games.test');

        // A venue refuses to open without one, and these tests are a venue
        // opening. Not a real secret and not one anything here checks against
        // the outside world.
        $app['config']->set('streetmesh.venue.secret', 'a-secret-shared-with-the-hub');

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
        // A venue has no users of its own. These are here because a server can
        // be a domicile too, and one screen behaves differently when it is.
        $this->loadLaravelMigrations();

        $this->loadMigrationsFrom(__DIR__.'/../vendor/streetmesh/protocol-laravel/database/migrations');

        // This package's own, which hold what a venue remembers about what is
        // happening here.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
