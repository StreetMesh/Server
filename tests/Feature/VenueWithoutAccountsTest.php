<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * A venue that holds no accounts.
 *
 * The arrangement Tabletop actually runs in: the venue capability on, the
 * domicile switched off. Nobody lives here. People arrive holding permission
 * from a server somewhere else, and this one has no account for them and never
 * will.
 *
 * Everything the starter kit assumes about a signed-in person is wrong on a
 * server like that, and it fails in the least helpful way — a 500, about
 * somebody clicking a link in a menu.
 */
class VenueWithoutAccountsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The one that was live: `auth` sends a guest to `route('login')`, and the
     * domicile package is what brings that route. Without it, every page behind
     * the door answered "Route [login] not defined".
     */
    public function test_a_guest_is_sent_somewhere_that_exists(): void
    {
        $this->assertTrue(Route::has('login'), 'this host has a domicile, so the ordinary path still applies');

        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    /**
     * And the same request on a server with nowhere to sign in.
     *
     * Faked by hiding the route rather than by uninstalling a package, because
     * what the middleware asks is whether the route exists — which is exactly
     * the condition a venue-only server is in.
     */
    public function test_a_guest_on_a_server_with_no_sign_in_lands_on_the_front_page(): void
    {
        Route::getRoutes()->refreshNameLookups();

        $without = collect(Route::getRoutes()->getRoutes())
            ->reject(fn ($route): bool => $route->getName() === 'login');

        $collection = new RouteCollection;

        foreach ($without as $route) {
            $collection->add($route);
        }

        Route::setRoutes($collection);

        $this->assertFalse(Route::has('login'));

        $this->get('/dashboard')->assertRedirect(url('/'));
    }

    /**
     * And it is not offered in the first place.
     *
     * The menu asked whether any capability offers a panel, which the venue
     * does. It should also have asked whose page it is: the home page sits
     * behind `auth`, so it belongs to a resident, and a venue's visitor is not
     * one however thoroughly they have arrived.
     */
    public function test_the_menu_offers_no_home_to_somebody_who_does_not_live_here(): void
    {
        $this->get('/experiences')
            ->assertOk()
            ->assertDontSee(route('dashboard'), escape: false);
    }
}
