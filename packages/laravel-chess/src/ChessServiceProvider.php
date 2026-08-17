<?php

namespace StreetMesh\Chess;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use StreetMesh\Protocol\Laravel\Capabilities\Capabilities;
use StreetMesh\Venue\Experiences\Experiences;

class ChessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Games::class);
    }

    public function boot(): void
    {
        /*
         * Registered with the venue rather than with the server. Chess is
         * something you can do here, not something this server is.
         */
        $this->app->make(Experiences::class)->register(new ChessExperience);

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'chess');

        /*
         * Livewire keeps a register of component namespaces separate from
         * Blade's, and consults only what `addNamespace` gave it. Both are
         * needed for a package to ship a screen.
         */
        Livewire::addNamespace('chess', viewPath: __DIR__.'/../resources/views/livewire');

        /*
         * An experience is something a venue hosts, so it has no screens on a
         * server that is not one. A domicile with this package installed used
         * to serve a chess lobby that could never open a table — there is no
         * hub behind it and no ticket to be had.
         *
         * `offers` rather than `has`, because this package boots before the
         * venue registers itself — asking the registry here answers "no" on a
         * server that is plainly a venue.
         */
        if (! $this->app->make(Capabilities::class)->offers('venue')) {
            return;
        }

        $this->app['router']
            ->middleware('web')
            ->group(__DIR__.'/../routes/web.php');
    }
}
