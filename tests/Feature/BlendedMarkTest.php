<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One server, two names.
 *
 * This host installs both halves, which is the arrangement the whole thing
 * exists for: a venue people arrive at, and a domicile answering for the
 * records of the people who live here. They are two things to be, and a person
 * looking at a screen should be able to tell which one they are dealing with.
 *
 * These are host tests rather than package tests because neither package can
 * see the other. Only a server with both installed can be wrong about this.
 */
class BlendedMarkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('streetmesh.venue.mark', 'brand/tabletop-mark');
        config()->set('streetmesh.domicile.mark', null);
    }

    /**
     * The door belongs to the venue, so it wears the venue's mark.
     */
    public function test_the_door_wears_the_venue_mark(): void
    {
        $door = $this->get('/connect')->assertOk()->getContent();

        $this->assertStringContainsString('tabletop-mark-small.svg', $door);
        $this->assertStringContainsString('tabletop-mark-dark-small.svg', $door);
    }

    /**
     * And the consent screen does not, because it is not the venue speaking.
     *
     * This is a domicile being asked for permission by a venue somewhere else.
     * The venue in this same container has nothing to do with that exchange,
     * and wearing its mark here would say the two parties are one party —
     * which is the single thing the handshake exists to deny.
     */
    public function test_the_consent_screen_wears_the_domicile_mark(): void
    {
        $screen = view('streetmesh::consent', [
            'venue' => 'games.example',
            'asking' => ['Add a game to your records'],
            'permission' => (object) ['request_uri' => 'urn:whatever'],
        ])->render();

        $this->assertStringContainsString('streetmesh-mark-small.svg', $screen);
        $this->assertStringNotContainsString('tabletop', $screen);
    }

    /**
     * A server asking itself shows both of its own faces.
     *
     * The ordinary case on a blended server, and the one that looked wrong:
     * both sides named `server.test`, and the half with a mark of its own was
     * drawn as an anonymous shop front. Its mark is not a stranger's — it is
     * configured in this same container.
     */
    public function test_a_venue_that_is_this_server_shows_its_own_mark(): void
    {
        $screen = view('streetmesh::consent', [
            'venue' => config('streetmesh.host'),
            'asking' => ['Add a game to your records'],
            'permission' => (object) ['request_uri' => 'urn:whatever'],
        ])->render();

        $this->assertStringContainsString('tabletop-mark-small.svg', $screen, 'the venue half');
        $this->assertStringContainsString('streetmesh-mark-small.svg', $screen, 'the domicile half');

        /*
         * Two marks drawn, counted rather than named. Each is a light and a
         * dark file, so both sides wearing one is four references — and the
         * fallback glyph is inlined SVG with no name left in the markup to
         * assert on, which is how a test for its absence passed while proving
         * nothing.
         */
        $this->assertSame(4, substr_count($screen, '/brand/'));
    }

    /**
     * A venue somewhere else is drawn from its own origin.
     *
     * The convention every venue serves, so a hostname is enough to build the
     * address and nothing has to be fetched, negotiated or believed. Only
     * `games.example` can put a picture at `games.example`, which is what makes
     * it worth showing at all — a copy served from here would be this server
     * vouching for a likeness it cannot check.
     */
    public function test_a_venue_elsewhere_is_drawn_from_its_own_origin(): void
    {
        $screen = view('streetmesh::consent', [
            'venue' => 'games.example',
            'asking' => ['Add a game to your records'],
            'permission' => (object) ['request_uri' => 'urn:whatever'],
        ])->render();

        $this->assertStringContainsString('https://games.example/mark.svg', $screen);
        $this->assertStringContainsString('https://games.example/mark-dark.svg', $screen);

        // Nothing of this server's venue half leaks onto somebody else's side.
        $this->assertStringNotContainsString('tabletop', $screen);
    }

    /**
     * A name that is not a hostname buys nobody an address.
     *
     * This is built from a string that arrived over the wire, and the one thing
     * it must never do is point somebody's browser somewhere a stranger chose.
     */
    public function test_a_venue_that_is_not_a_hostname_is_drawn_as_nothing(): void
    {
        $screen = view('streetmesh::consent', [
            'venue' => '',
            'asking' => ['Add a game to your records'],
            'permission' => (object) ['request_uri' => 'urn:whatever'],
        ])->render();

        $this->assertStringNotContainsString('/mark.svg', $screen);
    }

    /**
     * The sidebar too, which is the screen somebody actually spends time on.
     *
     * Chrome belongs to no capability in particular, so it wears the mark of
     * whichever one greets people. A venue-only server is that venue
     * everywhere — not on the two screens somebody remembered to label.
     */
    public function test_the_chrome_wears_the_venue_mark_on_a_venue(): void
    {
        config()->set('streetmesh.front_page', 'venue');

        $this->actingAs(User::factory()->create())
            ->get('/experiences')
            ->assertOk()
            ->assertSee('tabletop-mark-small.svg', escape: false);
    }

    /**
     * Configuring nothing changes nothing.
     *
     * The point of a default is that a server nobody has branded looks the way
     * it always did, and that removing the configuration is a safe thing to do.
     */
    public function test_a_venue_nobody_branded_wears_the_servers_own_mark(): void
    {
        config()->set('streetmesh.venue.mark', null);

        $this->get('/connect')
            ->assertOk()
            ->assertSee('streetmesh-mark-small.svg', escape: false)
            ->assertDontSee('tabletop', escape: false);
    }
}
