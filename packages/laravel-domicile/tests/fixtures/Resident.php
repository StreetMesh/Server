<?php

namespace StreetMesh\Domicile\Tests\Fixtures;

use Illuminate\Foundation\Auth\User;

/**
 * Stands in for the host application's user model.
 *
 * A domicile does not ship one — whose accounts these are is the application's
 * business. What this package decides is what happens when one of them is given
 * an address.
 */
class Resident extends User
{
    protected $table = 'users';

    protected $guarded = [];
}
