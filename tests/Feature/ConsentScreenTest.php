<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The screen where somebody decides.
 *
 * This server overrides the protocol package's plain fallback with its own, and
 * an override is a copy — so the words that make the decision informed can drift
 * out of it silently while the package's version stays correct and unread. That
 * is the whole reason this file exists.
 *
 * The view is rendered directly rather than reached through the OAuth flow. What
 * is being checked is what the screen says, and standing up a real pending
 * permission to check it would be testing the protocol package's job twice.
 */
class ConsentScreenTest extends TestCase
{
    private function screen(string $venue = 'games.example'): string
    {
        return view('streetmesh::consent', [
            'venue' => $venue,
            'asking' => ['Add com.streetmesh.games.chess to your records, and never change or remove them'],
            'permission' => (object) ['request_uri' => 'urn:ietf:params:oauth:request_uri:whatever'],
        ])->render();
    }

    /**
     * Who is asking, named. A consent screen that does not say which server
     * wants this is asking somebody to agree to nothing in particular.
     */
    public function test_it_names_the_venue_and_says_what_is_wanted(): void
    {
        $screen = $this->screen('server.test');

        $this->assertStringContainsString('server.test wants to connect', $screen);
        $this->assertStringContainsString('Permissions you&#039;re granting:', $screen);
        $this->assertStringContainsString('com.streetmesh.games.chess', $screen);
    }

    /**
     * Refusing has to be exactly as easy as agreeing — a full button beside the
     * other, not a link tucked underneath it.
     */
    public function test_refusing_is_as_easy_as_agreeing(): void
    {
        $screen = $this->screen();

        $this->assertStringContainsString('Allow', $screen);
        $this->assertStringContainsString('Cancel', $screen);
        $this->assertStringContainsString('You can revoke this at any time.', $screen);
    }

    /**
     * The door and this screen are two halves of one handover, and the panel
     * swapping sides is what shows somebody they have crossed between servers.
     * Losing the flip would leave two screens that look like the same page.
     */
    public function test_it_wears_the_door_with_the_panel_on_the_other_side(): void
    {
        $this->assertStringContainsString('lg:flex-row-reverse', $this->screen());
    }

    /**
     * A Flux tag arriving as text means Blade could not parse the attribute
     * before it and gave up, swallowing the rest of the markup — including,
     * here, the buttons somebody answers with.
     */
    public function test_the_whole_screen_rendered(): void
    {
        $this->assertStringNotContainsString('<flux:', $this->screen());
    }
}
