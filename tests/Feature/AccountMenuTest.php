<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use StreetMesh\Protocol\Laravel\Permissions\Delegation;
use StreetMesh\Protocol\P256;
use StreetMesh\Venue\Visitors;
use Tests\TestCase;

/**
 * The account menu, of which there are two.
 *
 * One in the sidebar on a wide screen and one in the header on a narrow one.
 * They were two copies of the same markup and drifted the moment anything was
 * added to either: "Revoke access" went into the sidebar and not the header, so
 * on a phone there was no way to give a permission back at all.
 *
 * Both are on the page at once — which is showing is CSS — so asserting on the
 * response is asserting on both. Counting is the point: one of each means the
 * two menus agree.
 */
class AccountMenuTest extends TestCase
{
    use RefreshDatabase;

    private function visiting(): User
    {
        $user = User::factory()->create();

        $delegation = Delegation::create([
            'did' => 'did:web:alice.home.test',
            'handle' => 'alice.home.test',
            'issuer' => 'https://home.test',
            'dpop_key' => Delegation::store(P256::generate()),
            'access_token' => 'a-live-token',
            'scope' => 'atproto',
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->withSession([Visitors::SESSION_KEY => $delegation->id]);

        return $user;
    }

    public function test_both_menus_offer_to_give_the_permission_back(): void
    {
        $page = (string) $this->actingAs($this->visiting())
            ->get('/experiences')
            ->assertOk()
            ->getContent();

        $this->assertSame(
            2,
            substr_count($page, 'Revoke access'),
            'the sidebar and the header must offer the same things',
        );

        $this->assertSame(2, substr_count($page, 'Log out'));
        $this->assertStringContainsString('alice.home.test', $page);
    }

    /**
     * Nothing to give back when nobody is visiting, and the menu still works.
     * A domicile with no venue installed must not grow a dead control.
     */
    public function test_somebody_who_is_not_visiting_is_not_offered_it(): void
    {
        $page = (string) $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Revoke access', $page);
        $this->assertSame(2, substr_count($page, 'Log out'), 'the rest of the menu is still there');
    }
}
