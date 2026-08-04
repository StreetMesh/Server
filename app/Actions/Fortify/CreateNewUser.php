<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use StreetMesh\Domicile\Residents\AvailableAddress;
use StreetMesh\Domicile\Residents\Handle;
use StreetMesh\Domicile\Residents\Residents;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private readonly Residents $residents) {}

    /**
     * Validate and create a newly registered user.
     *
     * Registering here is two things at once: an account, which is this
     * server's business, and an address, which is not. The address is what
     * makes somebody able to go anywhere — without one they can sign in, and
     * that is all they can ever do.
     *
     * Both or neither. An account with no address is somebody who cannot leave
     * the building, and it is not a state worth being able to reach.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'address' => ['required', 'string', new AvailableAddress($this->residents)],
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $this->residents->settle(
                $user,
                Handle::for($input['address'], $this->residents->host()),
            );

            return $user;
        });
    }
}
