<?php

namespace StreetMesh\Venue\Tests\Fixtures;

use Illuminate\Foundation\Auth\User;

/**
 * Stands in for the host application's user model.
 *
 * A venue has no users — there is nothing here to sign in to. This exists
 * because a single server can be a domicile as well, and the door behaves
 * differently for somebody that half of the server already knows.
 */
class Resident extends User
{
    protected $table = 'users';

    protected $guarded = [];
}
