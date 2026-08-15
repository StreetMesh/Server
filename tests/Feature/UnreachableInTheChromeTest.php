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
}
