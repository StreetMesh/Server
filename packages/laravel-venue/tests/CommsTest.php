<?php

namespace StreetMesh\Venue\Tests;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use StreetMesh\Protocol\Laravel\Capabilities\Capabilities;
use StreetMesh\Protocol\Laravel\Permissions\Delegation;
use StreetMesh\Protocol\P256;
use StreetMesh\Venue\Comms;
use StreetMesh\Venue\Parties\Parties;
use StreetMesh\Venue\Visitors;

/**
 * The comms widget, as three documents.
 *
 * Each is served into an iframe of its own, and that is the shape rather than
 * an implementation detail: the badge sits above every experience's screen
 * without inheriting its stylesheet or its stacking context, and the stage
 * holds a live microphone that a re-render of somebody's game must not be able
 * to take away.
 *
 * What is worth testing from here is what each document is *for* — that the
 * badge is a badge and nothing else, that the stage knows which party it
 * belongs to, and that the panel offers the right two conversations.
 */
class CommsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('streetmesh.venue.parties.enabled', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * These documents load the application's built assets, and a package
         * has no build. What is being tested is what they say rather than what
         * they load, so the tags are stubbed out — the alternative is a
         * manifest checked into a package that would go stale on its own.
         */
        $this->withoutVite();
    }

    private function visitor(string $who = 'alice'): Delegation
    {
        return Delegation::create([
            'did' => 'did:web:'.$who.'.home.test',
            'handle' => $who.'.home.test',
            'issuer' => 'https://home.test',
            'dpop_key' => Delegation::store(P256::generate()),
            'access_token' => 'a-live-token',
            'scope' => 'atproto',
            'expires_at' => now()->addMinutes(15),
        ]);
    }

    /**
     * @return TestResponse<Response>
     */
    private function as(Delegation $who, string $surface): TestResponse
    {
        return $this->withSession([Visitors::SESSION_KEY => $who->id])
            ->get(route('venue.comms.'.$surface));
    }

    private function parties(): Parties
    {
        return $this->app->make(Parties::class);
    }

    /**
     * Somewhere to look at the snippet a host layout includes.
     *
     * The widget belongs in an application's chrome, which a package has none
     * of — but what it writes into the page is now the thing that matters most,
     * because the page is what holds the camera.
     */
    protected function defineRoutes($router): void
    {
        $router->get('comms-host', fn () => View::make('venue::comms.widget'))->middleware('web');
    }

    public function test_the_badge_is_a_circle_and_nothing_else(): void
    {
        $badge = $this->get(route('venue.comms.badge'))->assertOk();

        $badge->assertSee('border-radius: 9999px', escape: false);

        /*
         * Deliberately self-contained. The badge is the first thing anybody
         * sees, and pulling the application's whole stylesheet in for one
         * circle would make it the slowest.
         */
        $badge->assertDontSee('livewire', escape: false);
    }

    /**
     * A passer-by gets a badge with an invitation behind it rather than an
     * empty panel. Watching a public game asks nothing of anybody, so the
     * corner of the screen should not be the thing that starts demanding.
     */
    public function test_somebody_who_has_not_arrived_is_offered_the_door(): void
    {
        $this->get(route('venue.comms.panel'))
            ->assertOk()
            ->assertSee('Talking needs a name')
            ->assertSee(route('venue.connect'), escape: false);
    }

    public function test_the_panel_offers_both_conversations(): void
    {
        $this->as($this->visitor(), 'panel')
            ->assertOk()
            ->assertSee('Room')
            ->assertSee('Party');
    }

    public function test_the_party_tab_offers_the_two_ways_in(): void
    {
        $this->as($this->visitor(), 'panel')
            ->assertOk()
            ->assertSee('Start a party')
            ->assertSee('Code');
    }

    /**
     * The camera and microphone are reachable without a party.
     *
     * They started inside the party branch, which is where they were useless:
     * your own circle only appears once something is turned on, so somebody
     * with no party and nothing on had no switch anywhere on screen and no way
     * to get started at all. A camera is yours rather than the party's.
     */
    public function test_the_camera_can_be_turned_on_without_a_party(): void
    {
        $panel = $this->as($this->visitor(), 'panel')->assertOk();

        /*
         * The words are the accessible name now rather than a label, because
         * the buttons are icons — so they are asserted where they actually
         * live, which is also the only place anything but a pair of eyes will
         * find them.
         */
        $panel->assertSee('aria-label="Speak"', escape: false);
        $panel->assertSee('aria-label="Show"', escape: false);
        $panel->assertSee('streetmesh.panel.speak', escape: false);
        $panel->assertSee('streetmesh.panel.show', escape: false);
    }

    /**
     * And nowhere at all at a venue that offers no parties.
     *
     * They used to be on both tabs, which put a microphone under a text
     * conversation and offered a camera at a venue with nobody to point it at:
     * media is carried between the people in a party, so with no parties on
     * offer these switch on a preview of yourself and nothing else.
     *
     * Still reachable before a party exists — they follow the party *tab*,
     * which is there whenever parties are, rather than a party, which is not.
     */
    public function test_the_switches_are_not_offered_where_parties_are_not(): void
    {
        config(['streetmesh.venue.parties.enabled' => false]);

        $this->as($this->visitor(), 'panel')
            ->assertOk()
            ->assertDontSee('Start a party')
            ->assertDontSee('streetmesh.panel.speak', escape: false)
            ->assertDontSee('streetmesh.panel.show', escape: false);
    }

    /**
     * A word you can say across a table. Being able to do that is the whole
     * reason it exists, so it has to be on the panel where the party is.
     *
     * It lives in the drawer, which is drawn shut rather than not drawn — the
     * conversation is what the pane is for, and this is reference you read
     * once. So is the way out: leaving is rare, and putting it on show under
     * the conversation cost the room a strip that was mostly empty.
     */
    public function test_a_party_shows_the_word_that_lets_somebody_in(): void
    {
        $alice = $this->visitor();
        $party = $this->parties()->open($alice);

        $this->as($alice, 'panel')
            ->assertOk()
            ->assertSee($party->code)
            ->assertSee('Copy')
            ->assertSee('Really leave?')
            ->assertSee('Leave party');
    }

    /**
     * A switch that is off says so, rather than merely failing to say it is on.
     *
     * Colour alone asks somebody to remember what the other state looked like,
     * and lit-versus-unlit is exactly the distinction a person glancing at
     * their own microphone cannot afford to get wrong.
     */
    public function test_the_switches_are_struck_through_when_they_are_off(): void
    {
        $panel = $this->as($this->visitor(), 'panel')->assertOk();

        /* One microphone, always drawn, with the line over it coming and
           going — there is no struck-through microphone in the set to swap
           to, and swapping is not possible anyway from a state the browser
           is holding. */
        $panel->assertSee('x-show="! speaking"', escape: false);
        $panel->assertSee('x-show="! showing"', escape: false);
    }

    /**
     * And a way to open it, beside the microphone and the camera.
     *
     * The three things this panel does, in one row. The count is on it because
     * how many of you there are is the one thing about a party worth reading
     * without opening anything.
     */
    public function test_the_party_drawer_has_a_switch_of_its_own(): void
    {
        $alice = $this->visitor();
        $this->parties()->open($alice);

        $panel = $this->as($alice, 'panel')->assertOk();

        $panel->assertSee('aria-label="Who is here"', escape: false);
        $panel->assertSee('drawer = ! drawer', escape: false);
    }

    /** And no switch where there is no party to look into. */
    public function test_there_is_no_switch_before_there_is_a_party(): void
    {
        $this->as($this->visitor(), 'panel')
            ->assertOk()
            ->assertDontSee('aria-label="Who is here"', escape: false);
    }

    /**
     * The strip says how many people are in it without being opened.
     *
     * That is the whole question most of the time, and answering it in the one
     * row that is always visible means the drawer stays shut.
     */
    public function test_the_party_strip_says_how_many_are_in_it(): void
    {
        $alice = $this->visitor();
        $bob = $this->visitor('bob');

        $party = $this->parties()->open($alice);
        $this->parties()->accept($this->parties()->invite($party, $alice, (string) $bob->did), $bob);

        $this->as($alice, 'panel')->assertOk()->assertSee('Party of 2');
    }

    /**
     * The stage holds nothing and knows nothing.
     *
     * It used to hold the media, and could not: a navigation reloads every
     * iframe on the page, and a stream cannot outlive the document that
     * acquired it. What is left is a stylesheet and somewhere to put faces —
     * the page around it draws them, which it may do because they are the same
     * origin.
     */
    public function test_the_stage_is_a_shell(): void
    {
        $this->as($this->visitor(), 'stage')
            ->assertOk()
            ->assertSee('id="stage"', escape: false)
            ->assertDontSee('streetmeshStage', escape: false)
            ->assertDontSee('stage-frame', escape: false);
    }

    /**
     * A circle can show that its person could not be reached.
     *
     * Including the `[hidden]` rule for it, which is not a detail. A `display`
     * set anywhere else beats the attribute, and what that looks like is two
     * marks contradicting each other on one circle — the same fault that once
     * showed both chat icons at the same time.
     */
    public function test_a_face_can_show_that_nobody_could_reach_it(): void
    {
        $this->as($this->visitor(), 'stage')
            ->assertOk()
            ->assertSee('.face .lost {', escape: false)
            ->assertSee('.face .lost[hidden]', escape: false)
            ->assertSee('.face.lost .avatar', escape: false);
    }

    /**
     * Each surface says it may be framed by this origin.
     *
     * Nothing local objects to framing, so this was found only once it was
     * deployed: a proxy in front of the venue added `X-Frame-Options: deny` to
     * everything it served, which refuses same-origin framing as flatly as any
     * other kind. All three came back 200 and none of them appeared.
     *
     * `frame-ancestors` is the one that decides it — a browser reading both
     * applies the policy and ignores the older header — which is why it is
     * asserted here even though the proxy's own answer is the one that will
     * arrive beside it.
     */
    public function test_the_surfaces_say_they_may_be_framed(): void
    {
        foreach (['badge', 'panel', 'stage'] as $surface) {
            $this->as($this->visitor(), $surface)
                ->assertOk()
                ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'")
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        }
    }

    /**
     * The party's addresses go to the page, because the page is what will be
     * using them.
     */
    public function test_the_page_is_told_which_party_to_join(): void
    {
        $alice = $this->visitor();
        $party = $this->parties()->open($alice);

        $this->withSession([Visitors::SESSION_KEY => $alice->id])
            ->get('/comms-host')
            ->assertOk()
            ->assertSee($party->key)
            ->assertSee(route('venue.parties.ticket', $party->key), escape: false)
            ->assertSee(route('venue.parties.signals', $party->key), escape: false)
            ->assertSee($alice->handle);
    }

    public function test_somebody_in_no_party_gives_the_page_nothing_to_join(): void
    {
        $this->withSession([Visitors::SESSION_KEY => $this->visitor()->id])
            ->get('/comms-host')
            ->assertOk()
            ->assertSee('"party":null', escape: false);
    }

    /**
     * A server that is not a venue has none of this, whatever the setting says.
     *
     * The widget is included by the application's chrome, which a domicile
     * draws too — and the routes behind it belong to the venue package's own
     * group, which is never registered on a server that is not a venue. Left to
     * the setting alone, a domicile emitted three frames pointing at three
     * addresses it does not have.
     */
    public function test_a_server_that_is_not_a_venue_offers_nothing(): void
    {
        config(['streetmesh.venue.comms.enabled' => true]);

        /* The switch the operator sets, which is settled before anything
           boots — see `Capabilities::offers`. */
        $this->app->instance(
            Capabilities::class,
            new Capabilities(['venue' => false]),
        );

        $this->assertFalse($this->app->make(Comms::class)->offered());
    }

    public function test_a_venue_with_comms_off_has_none(): void
    {
        config(['streetmesh.venue.comms.enabled' => false]);

        $this->get(route('venue.comms.badge'))->assertNotFound();
        $this->get(route('venue.comms.panel'))->assertNotFound();
        $this->get(route('venue.comms.stage'))->assertNotFound();
    }
}
