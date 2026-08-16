<?php

namespace StreetMesh\Protocol\Laravel\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use StreetMesh\Protocol\Laravel\ProtocolServiceProvider;
use StreetMesh\Protocol\Laravel\Records\Record;
use StreetMesh\Protocol\Network;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [ProtocolServiceProvider::class];
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
        // Keys are encrypted at rest, so a server without an application key
        // cannot hold an identity at all. Worth failing loudly in tests rather
        // than discovering it on a first deploy.
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

        /*
         * Testbench answers on http://localhost, and this package refuses to
         * publish a document naming anything reachable in the clear. That rule
         * is not relaxed for tests: a real deployment is served over TLS — Herd
         * provides it locally, and anywhere worth deploying to provides it in
         * production — so a test environment on plain http would be exercising
         * a situation that never occurs while hiding the check that matters.
         */
        $app['config']->set('app.url', 'https://games.test');
        $app['url']->forceRootUrl('https://games.test');
        $app['url']->forceScheme('https');

        $app['config']->set('streetmesh.collections', [
            'com.streetmesh.games.chess' => Record::PUBLIC,
            'com.streetmesh.messages.direct' => Record::PRIVATE,
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
