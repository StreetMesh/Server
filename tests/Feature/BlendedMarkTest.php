<?php

namespace Tests\Feature;

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
     * And a venue somewhere else stays a shop front, because there is no way to
     * know what it looks like and no fetching a stranger's image into a
     * permission screen to find out.
     */
    public function test_a_venue_elsewhere_stays_anonymous(): void
    {
        $screen = view('streetmesh::consent', [
            'venue' => 'games.example',
            'asking' => ['Add a game to your records'],
            'permission' => (object) ['request_uri' => 'urn:whatever'],
        ])->render();

        $this->assertStringNotContainsString('tabletop', $screen);

        // One mark, in its two grounds: this server's, and a glyph beside it.
        $this->assertSame(2, substr_count($screen, '/brand/'));
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
