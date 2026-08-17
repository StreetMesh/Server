<?php

namespace StreetMesh\Venue;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use RuntimeException;
use StreetMesh\Protocol\Laravel\Capabilities\Capabilities;
use StreetMesh\Venue\Console\BuildHub;
use StreetMesh\Venue\Console\DeployHub;
use StreetMesh\Venue\Console\TidyGatherings;
use StreetMesh\Venue\Console\TidyParties;

class VenueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /*
         * Merged under the protocol's own key rather than a second root, so an
         * operator configuring a server reads one file rather than one per
         * package installed.
         */
        $this->mergeConfigFrom(__DIR__.'/../config/venue.php', 'streetmesh.venue');

        $this->app->singleton(Visitors::class);
        $this->app->singleton(Experiences\Experiences::class);
        $this->app->singleton(Gatherings\Gatherings::class);
        $this->app->singleton(Gatherings\Results::class);
        $this->app->singleton(Parties\Parties::class);
        $this->app->singleton(Chat\Chat::class);
        $this->app->singleton(Media\Mailbox::class);
        $this->app->singleton(Comms::class);
        $this->app->singleton(Realtime\Secrets::class);
        $this->app->singleton(Realtime\Occupancy::class);
    }

    public function boot(): void
    {
        $this->app->make(Capabilities::class)->register(new VenueCapability);

        $this->refuseUnlessEquipped();
        $this->protectSessionDescriptions();

        if ($this->app->runningInConsole()) {
            $this->commands([BuildHub::class, DeployHub::class, TidyGatherings::class, TidyParties::class]);
        }

        /*
         * Scheduled by the package rather than left to an operator to wire up.
         * Tables nobody came to are a consequence of running a venue at all, so
         * clearing them is part of being one — and the failure mode of
         * forgetting is a lobby that fills with invitations nobody can accept.
         *
         * Every five minutes, against a ten-minute wait: often enough that a
         * lobby stays honest, rarely enough that the hub is barely asked.
         */
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(TidyGatherings::class)->everyFiveMinutes()->withoutOverlapping();

            /*
             * The same cadence, and a much cheaper sweep — this one asks the
             * database whether anybody is still a member and never troubles the
             * hub. See the command for why a party can empty without anybody
             * having left.
             */
            $schedule->command(TidyParties::class)->everyFiveMinutes()->withoutOverlapping();
        });

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'venue');

        /*
         * Livewire keeps its own register of namespaces, separate from Blade's.
         * `loadViewsFrom` above is what makes `venue::front` resolvable as a
         * view; it does nothing for `<livewire:venue::experiences />`, because
         * Livewire's finder consults only what `addNamespace` gave it. Both are
         * needed, and this is exactly how Livewire registers its own `pages`
         * and `layouts` namespaces on boot.
         *
         * No ⚡ in the filename on purpose — an emoji in a path that Composer
         * has to install is a problem nobody needs.
         */
        Livewire::addNamespace('venue', viewPath: __DIR__.'/../resources/views/livewire');

        /*
         * "Is somebody here", which is not the same question as "is somebody
         * signed in". A venue has no accounts, so the framework's own guards
         * have nothing to check — what this asks is whether this browser is
         * acting under permission somebody's own server gave us.
         */
        $this->app['router']->aliasMiddleware('visitor', Http\RequireVisitor::class);

        /*
         * Whether the menu is anybody's business, asked per request rather than
         * when routes are registered — a setting consulted at boot gets baked
         * into a cached route table and appears to do nothing.
         */
        $this->app['router']->aliasMiddleware('venue.menu', Http\GuardTheMenu::class);

        /*
         * Whether this venue does parties at all, asked the same way and for
         * the same reason as the line above it.
         */
        $this->app['router']->aliasMiddleware('parties', Http\RequireParties::class);

        /*
         * At its own name, with no prefix. There is nothing here another
         * capability would also want, so there is nothing to arrange — the two
         * surfaces that overlap, the front page and the home page, belong to
         * the application.
         */
        /*
         * Server to server, so none of the browser middleware. See the file.
         *
         * Registered before the switch below, and deliberately: a hub may still
         * be finishing something that was started before an operator turned the
         * venue off, and refusing to hear about it would lose a game rather
         * than close a door.
         */
        $this->app['router']->middleware([])->group(__DIR__.'/../routes/realtime.php');

        /*
         * Switched off means gone, not hidden — the same rule the domicile
         * follows. A server that is not a venue has no door to one, and no
         * menu of things to do at it.
         */
        if (! $this->app->make(Capabilities::class)->offers('venue')) {
            return;
        }

        $this->app['router']
            ->middleware('web')
            ->group(__DIR__.'/../routes/web.php');

    }

    /**
     * Keep the framework's tidying away from a session description.
     *
     * Laravel blanks and trims request input as a kindness to HTML forms. An
     * SDP is not a form field: it is a line-oriented document whose every line
     * ends in CRLF, including the last one. Trimming takes that terminator off
     * and what arrives is a document the far side refuses with "Invalid SDP
     * line" — after which every ICE candidate for it fails with "the remote
     * description was null", because there is no description to attach them to.
     *
     * The same guarantee `Protocol-Laravel` already gives signed documents, and
     * for the same reason: bytes that mean something to somebody else must
     * arrive as they were sent. It is written out again here rather than shared
     * because the paths are the venue's own, and because a signalling route is
     * not a signed one — the reason they need it is different even though the
     * damage is identical.
     */
    private function protectSessionDescriptions(): void
    {
        $carriesDescription = static fn (Request $request): bool => $request->is('parties/*/signals');

        ConvertEmptyStringsToNull::skipWhen($carriesDescription);
        TrimStrings::skipWhen($carriesDescription);
    }

    /**
     * A venue that cannot do the job does not open.
     *
     * What counts as equipped is decided by `Readiness`; this only asks, and
     * turns an answer into a stop.
     *
     * Not in the console, which has to keep working — `key:generate` and
     * `migrate` are how a server gets to the point of having either of the
     * things being asked about.
     */
    private function refuseUnlessEquipped(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        $readiness = new Readiness(
            isVenue: $this->app->make(Capabilities::class)->has('venue'),
            hasSecret: $this->app->make(Realtime\Secrets::class)->configured(),
            hub: config('streetmesh.venue.hub'),
            parties: (bool) config('streetmesh.venue.parties.enabled', false),
            partySize: (int) config('streetmesh.venue.parties.size', 0),
        );

        $missing = $readiness->missing();

        if ($missing !== null) {
            throw new RuntimeException($missing);
        }

        /*
         * Said rather than thrown. These are settings that will work and not the
         * way somebody asked for, which is not a reason to stay shut — but a
         * venue that silently did something other than what its configuration
         * says is exactly the failure this class exists to prevent.
         */
        foreach ($readiness->concerns() as $concern) {
            Log::warning($concern);
        }
    }
}
