<?php

namespace StreetMesh\Domicile;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use StreetMesh\Protocol\Laravel\Capabilities\Capabilities;
use StreetMesh\Protocol\Laravel\Records\Record;

class DomicileServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /*
         * Merged under the protocol's own key rather than a second root, so an
         * operator configuring a server reads one file rather than one per
         * package installed.
         */
        $this->mergeConfigFrom(__DIR__.'/../config/domicile.php', 'streetmesh.domicile');
    }

    public function boot(): void
    {
        $this->app->make(Capabilities::class)->register(new DomicileCapability);

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->declareAvatars();

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'domicile');

        /*
         * Livewire keeps its own register of namespaces, separate from Blade's.
         * `loadViewsFrom` above is what makes `domicile::front` resolvable as a
         * view; it does nothing for `<livewire:domicile::directory />`, because
         * Livewire's finder consults only what `addNamespace` gave it. Both are
         * needed, and this is exactly how Livewire registers its own `pages`
         * and `layouts` namespaces on boot.
         *
         * No ⚡ in the filename on purpose — an emoji in a path that Composer
         * has to install is a problem nobody needs.
         */
        Livewire::addNamespace('domicile', viewPath: __DIR__.'/../resources/views/livewire');

        /*
         * At its own name, with no prefix. There is nothing here another
         * capability would also want, so there is nothing to arrange — the two
         * surfaces that overlap, the front page and the home page, belong to
         * the application.
         */
        /*
         * A person who typed a resident's address into a browser is asking
         * about a person, not resolving a handle. See the middleware.
         */
        $this->app['router']->pushMiddlewareToGroup('web', Http\SendResidentsHome::class);

        /*
         * Switched off means gone, not hidden. A capability this server does
         * not offer has no screens here — otherwise a venue serves a directory
         * of residents it does not have, at a path a domicile in the same
         * container would want.
         */
        if (! $this->app->make(Capabilities::class)->offers('domicile')) {
            return;
        }

        $this->app['router']
            ->middleware('web')
            ->group(__DIR__.'/../routes/web.php');

        /*
         * And what a resident's own hostname serves, which is not a browser
         * route and must not be registered as one. See the file.
         */
        $this->loadRoutesFrom(__DIR__.'/../routes/published.php');
    }

    /**
     * Say that this server publishes what its residents look like.
     *
     * In `boot` rather than `register`, and that is not tidiness. `Collections`
     * is bound as a closure that reads this config the first time somebody
     * writes or reads a record, which never happens while providers are being
     * registered — so booting is late enough to be certain every provider has
     * had its say, and early enough that nothing has asked yet. Doing it in
     * `register` would depend on this package being registered after the
     * protocol's, which nothing guarantees.
     *
     * Only if nobody has already answered. Visibility is what a server
     * publishes, which is the operator's sentence to write; an operator who has
     * said these are private meant it, and what they get is residents whose
     * permalink answers with nothing rather than a setting quietly overruled.
     */
    private function declareAvatars(): void
    {
        /** @var array<string, string> $declared */
        $declared = (array) config('streetmesh.collections', []);

        if (array_key_exists(Avatars\Avatars::COLLECTION, $declared)) {
            return;
        }

        $declared[Avatars\Avatars::COLLECTION] = Record::PUBLIC;

        config(['streetmesh.collections' => $declared]);
    }
}
