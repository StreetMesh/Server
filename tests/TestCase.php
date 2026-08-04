<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use StreetMesh\Protocol\Network;

abstract class TestCase extends BaseTestCase
{
    /**
     * Nothing here talks to the outside world.
     *
     * Registering somebody mints an identity and publishes it, so this suite
     * reaches the network in the ordinary course of testing a sign-up form. A
     * package suite that did the same, pointed at the default, put about thirty
     * permanent entries in the public PLC directory for hosts that exist on one
     * laptop.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(Network::class, new OfflineNetwork);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
