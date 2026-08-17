<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every layout says what to do when the server cannot be reached.
 *
 * The behaviour belongs to `protocol-laravel` and is one partial. What is
 * tested here is the one thing that package cannot test: that this host
 * included it, in each of the six layouts it happens to have.
 *
 * Worth its own test because the failure is invisible until the worst moment.
 * A layout that quietly stopped including this looks perfectly well for as long
 * as the network holds, and then answers a dropped connection with a black
 * rectangle over somebody's game.
 */
class UnreachableInTheChromeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_screen_knows_what_to_say_when_it_cannot_reach_us(): void
    {
        $this->get(route('venue.experiences'))
            ->assertOk()
            ->assertSee('id="streetmesh-trouble"', escape: false)
            ->assertSee('Reconnecting', escape: false);
    }

    public function test_so_does_the_front_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('id="streetmesh-trouble"', escape: false);
    }

    /**
     * The script explains itself in a comment, and one of the words it needs is
     * the name of a Blade directive.
     *
     * Blade is a text preprocessor and knows nothing about JavaScript, so an
     * unescaped `@ livewireScripts` inside a `/* *\/` comment is compiled: Livewire's
     * own script tags are injected into the middle of the comment, the first of
     * them closes the surrounding script element, and the rest of the file lands
     * on the page as visible text in the corner of every screen.
     *
     * `assertSee` cannot catch that. It matches the raw source, where the text
     * is present either way — the same reason a form that vanished once passed
     * every test in this suite. What separates the two is whether Blade left the
     * directive alone, so that is what this asks.
     */
    public function test_the_script_explaining_itself_is_not_compiled(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('@livewireScripts', escape: false);
    }
}
