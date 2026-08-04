<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use StreetMesh\Domicile\Residents\Handle;
use StreetMesh\Domicile\Residents\Residents;
use StreetMesh\Protocol\Laravel\Identity\Identities;
use Tests\TestCase;

/**
 * This server is a domicile and a venue at once, which is the case the packages
 * cannot test on their own.
 *
 * Each of them proves its own half against a stand-in user model. What is only
 * true here is that the two halves agree about *this* application's user — the
 * venue looks somebody up by the morph class the domicile stored them under,
 * and a package test using its own fixture would pass either way.
 */
class ResidentAddressTest extends TestCase
{
    use RefreshDatabase;

    private function resident(): User
    {
        $user = User::factory()->create();
        $residents = app(Residents::class);

        $residents->settle($user, Handle::for('alice', $residents->host()));

        return $user;
    }

    public function test_somebody_who_lives_here_is_not_asked_to_type_their_own_address(): void
    {
        $this->actingAs($this->resident())
            ->get('/connect')
            ->assertOk()
            ->assertSee('value="alice.'.app(Residents::class)->host().'"', escape: false);
    }

    public function test_a_stranger_is_asked_for_an_address_with_nothing_filled_in(): void
    {
        $this->get('/connect')
            ->assertOk()
            ->assertSee('name="handle"', escape: false)
            ->assertDontSee('value="alice.', escape: false);
    }

    /**
     * A resident's name is a name under this server's own, so both well-known
     * documents stand for more than one identity and the hostname is what tells
     * them apart. Answered wrongly, every venue following alice's name would be
     * handed the server's identity instead of hers.
     */
    public function test_a_residents_name_resolves_to_them_and_not_to_the_server(): void
    {
        $this->resident();

        $host = 'alice.'.app(Residents::class)->host();

        $server = app(Identities::class)->forServer();
        $resident = app(Identities::class)->byHandle($host);

        $this->assertNotNull($resident);
        $this->assertNotSame($server->did, $resident->did);

        $this->get('http://'.$host.'/.well-known/atproto-did')
            ->assertOk()
            ->assertSee($resident->did);
    }
}
