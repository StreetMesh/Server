<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The comms widget is in this application's chrome.
 *
 * Everything it does belongs to the venue package and is tested there. What is
 * tested here is the one thing no package can test: that this host actually put
 * it on the page, in a layout the venue has never seen.
 *
 * Worth a test of its own because the failure is silent. A layout that quietly
 * stopped including it would leave every screen looking perfectly well, with no
 * way for anybody to talk to anyone and nothing anywhere complaining.
 */
class CommsInTheChromeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Including the mark for somebody nobody could reach.
     *
     * The icons are inlined into the page's own configuration rather than
     * loaded, so a missing one is not a broken request anywhere — it is a
     * circle that draws an empty box where the explanation should be.
     */
    public function test_the_chrome_carries_the_unreachable_mark(): void
    {
        $this->get(route('venue.experiences'))
            ->assertOk()
            ->assertSee('unreachable:', escape: false);
    }

    public function test_every_screen_carries_the_badge(): void
    {
        $this->get(route('venue.experiences'))
            ->assertOk()
            ->assertSee('streetmeshComms', escape: false)
            ->assertSee(route('venue.comms.badge'), escape: false);
    }

    /**
     * Including one inside an experience, which is the point of putting it in
     * the chrome rather than on a screen: a party spans everything installed.
     */
    public function test_it_is_there_inside_an_experience(): void
    {
        $this->get(route('chess.watch'))
            ->assertOk()
            ->assertSee('streetmeshComms', escape: false);
    }

    /**
     * A passer-by gets it too. Watching a public game asks nothing of anybody,
     * and the panel behind the badge offers them the door rather than an empty
     * corner of the screen.
     */
    public function test_a_passer_by_gets_one(): void
    {
        $this->get(route('venue.experiences'))
            ->assertOk()
            ->assertSee(route('venue.comms.panel'), escape: false);
    }

    public function test_a_venue_with_comms_off_carries_nothing(): void
    {
        config(['streetmesh.venue.comms.enabled' => false]);

        $this->get(route('venue.experiences'))
            ->assertOk()
            ->assertDontSee('streetmeshComms', escape: false);
    }
}
