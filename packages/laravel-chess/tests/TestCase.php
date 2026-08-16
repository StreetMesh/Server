<?php

namespace StreetMesh\Chess\Tests;

use Flux\FluxServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use StreetMesh\Chess\ChessServiceProvider;
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
        // An experience is not standalone: it sits on a venue, which sits on the
        // protocol. Booting it alone would be testing something nobody runs.
        return [
            LivewireServiceProvider::class,
            FluxServiceProvider::class,
            ProtocolServiceProvider::class,
            VenueServiceProvider::class,
            ChessServiceProvider::class,
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

        // Somewhere that is not anywhere. The network above is faked, so
        // nothing goes here; this only has to be set for the venue to be
        // willing to ask at all.
        $app['config']->set('streetmesh.venue.hub', 'wss://hub.invalid');
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
        $app['config']->set('app.url', 'https://games.test');
        $app['url']->forceRootUrl('https://games.test');
        $app['url']->forceScheme('https');

        $app['config']->set('livewire.component_namespaces.layouts', __DIR__.'/fixtures/views/layouts');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../vendor/streetmesh/protocol-laravel/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../vendor/streetmesh/laravel-venue/database/migrations');
    }
}
