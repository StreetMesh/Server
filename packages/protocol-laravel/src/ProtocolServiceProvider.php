<?php

namespace StreetMesh\Protocol\Laravel;

use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use StreetMesh\Protocol\Handle;
use StreetMesh\Protocol\Laravel\Attestations\Attestations;
use StreetMesh\Protocol\Laravel\Blobs\BlobStore;
use StreetMesh\Protocol\Laravel\Capabilities\Capabilities;
use StreetMesh\Protocol\Laravel\Console\CheckIdentity;
use StreetMesh\Protocol\Laravel\Http\LaravelNetwork;
use StreetMesh\Protocol\Laravel\Identity\DidResolver;
use StreetMesh\Protocol\Laravel\Identity\Identities;
use StreetMesh\Protocol\Laravel\Permissions\Delegations;
use StreetMesh\Protocol\Laravel\Permissions\Permissions;
use StreetMesh\Protocol\Laravel\Permissions\Spent;
use StreetMesh\Protocol\Laravel\Permissions\Tickets;
use StreetMesh\Protocol\Laravel\Plc\ImportIdentities;
use StreetMesh\Protocol\Laravel\Records\Collections;
use StreetMesh\Protocol\Laravel\Records\CommitLog;
use StreetMesh\Protocol\Laravel\Records\RecordStore;
use StreetMesh\Protocol\MerkleSearchTree;
use StreetMesh\Protocol\Network;
use StreetMesh\Protocol\PlcDirectory;
use StreetMesh\Protocol\RecordTree;

class ProtocolServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/streetmesh.php', 'streetmesh');

        $this->app->singleton(Network::class, fn (): Network => new LaravelNetwork(
            timeoutSeconds: (int) config('streetmesh.network.timeout', 10),
            cacheSeconds: (int) config('streetmesh.network.cache_seconds', 300),
        ));

        $this->app->singleton(Collections::class, fn (): Collections => new Collections(
            (array) config('streetmesh.collections', []),
        ));

        $this->app->singleton(RecordStore::class);
        $this->app->singleton(BlobStore::class);

        /*
         * The tree other people's software reads. A stranger holding the same
         * records computes the same root, so a commit is a claim anybody can
         * check rather than one only this server can.
         *
         * Bound rather than hard-wired because it was FlatTree until this line
         * changed, and because a server with reason to prefer something else
         * should not have to fork the package to say so.
         */
        $this->app->singleton(RecordTree::class, MerkleSearchTree::class);
        $this->app->singleton(CommitLog::class);

        $this->app->singleton(PlcDirectory::class, fn ($app): PlcDirectory => new PlcDirectory(
            $app->make(Network::class),
            (string) config('streetmesh.plc.directory', PlcDirectory::DEFAULT),
        ));

        $this->app->singleton(Handle::class, fn ($app): Handle => new Handle(
            $app->make(Network::class),
        ));

        $this->app->singleton(DidResolver::class);

        $this->app->singleton(Capabilities::class, fn ($app): Capabilities => new Capabilities(
            (array) config('streetmesh.capabilities', []),
        ));

        $this->app->singleton(Identities::class, fn ($app): Identities => new Identities(
            directory: $app->make(PlcDirectory::class),
            host: (string) config('streetmesh.host', 'localhost'),
            defaultCurve: (string) config('streetmesh.curve', 'p256'),
        ));
        $this->app->singleton(Attestations::class);

        $this->app->singleton(Spent::class);
        $this->app->singleton(Permissions::class);
        $this->app->singleton(Tickets::class);
        $this->app->singleton(Delegations::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        /*
         * Throttled but not authenticated. These answer who this server is, and
         * a record checkable only by somebody with an account here would be as
         * durable as the arrangement between two parties rather than outliving
         * it.
         */
        $this->app['router']->middlewareGroup('streetmesh', ['throttle:120,1']);

        $this->loadRoutesFrom(__DIR__.'/Http/routes.php');

        /*
         * The consent screen, and nothing else. Registered as a namespace so an
         * interface package can put its own `consent` view in front of this
         * one — what a resident sees is a domicile's business, and this is here
         * only so a bare server can still ask somebody a question.
         */
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'streetmesh');

        if ($this->app->runningInConsole()) {
            $this->commands([CheckIdentity::class, ImportIdentities::class]);
        }

        $this->publishes([
            __DIR__.'/../config/streetmesh.php' => config_path('streetmesh.php'),
        ], 'streetmesh-config');

        $this->protectSignedDocuments();
    }

    /**
     * Stop the framework tidying anything that carries a signature.
     *
     * Laravel blanks and trims request input as a kindness to HTML forms.
     * Applied to a signed document it is corruption: a signature covers bytes,
     * so turning an empty string into null changes what is being verified and
     * the check fails for a document that was never wrong. The failure looks
     * exactly like forgery, and it is data-dependent, so it appears
     * intermittent.
     *
     * This is the guarantee that most justifies the package existing. Every
     * implementor would otherwise have to know about it, and would find out the
     * same way — by losing two days.
     */
    private function protectSignedDocuments(): void
    {
        $carriesSignature = static fn (Request $request): bool => $request->is(
            ...(array) config('streetmesh.signed_paths', [
                'xrpc/*',
                'records', '*/records',
                'did.json', '*/did.json',
                '.well-known/*', '*/.well-known/*',
            ]),
        );

        ConvertEmptyStringsToNull::skipWhen($carriesSignature);
        TrimStrings::skipWhen($carriesSignature);
    }
}
