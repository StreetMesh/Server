<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Features;
use StreetMesh\Domicile\Residents\Residents;
use StreetMesh\Protocol\Laravel\Identity\Identities;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->register();

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    /**
     * Registering is two things: an account, which is this server's business,
     * and an address, which is not. Somebody with only the first can sign in
     * and do nothing else — they cannot authorize anything, cannot be delegated
     * from, and cannot visit a venue as themselves.
     *
     * Every account on this server was in that state, and nothing reported it,
     * because everything local worked.
     */
    public function test_registering_gives_somebody_an_address_and_not_only_an_account(): void
    {
        $this->register()->assertSessionHasNoErrors();

        $identity = app(Identities::class)->forUser(User::firstWhere('email', 'test@example.com'));

        $this->assertNotNull($identity, 'an account with no address cannot go anywhere');
        $this->assertSame('john.'.app(Residents::class)->host(), $identity->handle);
        $this->assertFalse($identity->is_server);
    }

    public function test_an_address_is_required(): void
    {
        $this->register(['address' => ''])->assertSessionHasErrors('address');

        $this->assertGuest();
    }

    public function test_an_address_somebody_already_has_is_refused(): void
    {
        $this->register()->assertSessionHasNoErrors();
        $this->post(route('logout'));

        $this->register(['email' => 'someone-else@example.com'])
            ->assertSessionHasErrors('address');
    }

    /**
     * Both halves or neither. A refused address must not leave an account
     * behind, or the person retries and is told their email is taken by an
     * account they cannot reach.
     */
    public function test_a_refused_address_leaves_no_account_behind(): void
    {
        $this->register(['address' => 'not a hostname'])->assertSessionHasErrors('address');

        $this->assertSame(0, User::query()->count());
    }

    /**
     * @param  array<string, string>  $overrides
     */
    private function register(array $overrides = []): TestResponse
    {
        return $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'address' => 'john',
            'password' => 'password',
            'password_confirmation' => 'password',
            ...$overrides,
        ]);
    }
}
