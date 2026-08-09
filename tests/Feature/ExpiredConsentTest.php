<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coming back to a permission screen that has nothing left to answer.
 *
 * Two ways to get here and neither is a mistake: a request lasts a few minutes,
 * and answering one empties it. So somebody who walked away from their desk, or
 * who reloaded after deciding, arrives at a handle that resolves to nothing.
 *
 * It used to be a RuntimeException, which is a 500 — a page that says the server
 * broke, about a person doing something entirely reasonable.
 */
class ExpiredConsentTest extends TestCase
{
    use RefreshDatabase;

    private const GONE = 'urn:ietf:params:oauth:request_uri:nothing-here';

    /*
     * Signed in, because this screen is behind a door and the person who hits
     * it has already come through — they were looking at the consent screen a
     * moment ago. An unauthenticated visitor is a different story with a
     * different answer, and the login redirect already tells it.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    /**
     * Gone rather than broken, and gone rather than missing: it was here, and
     * saying so stops a browser holding on to the page and offering it again.
     */
    public function test_a_request_that_has_gone_is_a_page_rather_than_a_crash(): void
    {
        $this->get('/oauth/authorize?request_uri='.self::GONE)
            ->assertStatus(410)
            ->assertSee('That request has expired');
    }

    /**
     * The first thing somebody wants to know, answered before anything else.
     */
    public function test_it_says_that_nothing_was_granted(): void
    {
        $this->get('/oauth/authorize?request_uri='.self::GONE)
            ->assertSee('Nothing was shared and nothing was granted.');
    }

    /**
     * A way onward, back to the place that asked. The permission that knew
     * where to send somebody is the thing that has gone, so this comes off the
     * client identifier instead.
     */
    public function test_it_offers_the_way_back_to_the_venue(): void
    {
        $this->get('/oauth/authorize?'.http_build_query([
            'request_uri' => self::GONE,
            'client_id' => 'https://games.example/client-metadata.json',
        ]))
            ->assertSee('Back to games.example')
            ->assertSee('https://games.example', escape: false);
    }

    /**
     * And somewhere to go when there is no venue to name — answering the form
     * carries the handle and nothing else.
     */
    public function test_answering_a_dead_request_lands_somewhere_rather_than_nowhere(): void
    {
        $this->post('/oauth/authorize', [
            'request_uri' => self::GONE,
            'answer' => 'yes',
        ])
            ->assertStatus(410)
            ->assertSee('Go to the front page');
    }
}
