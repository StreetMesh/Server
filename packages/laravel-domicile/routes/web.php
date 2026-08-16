<?php

use Illuminate\Support\Facades\Route;

/*
 * Two screens, at names nothing else wants, drawn by Livewire components this
 * package ships itself. They render into the host's layout — the package
 * decides what the screens are, the application decides what frames them.
 */
Route::livewire('directory', 'domicile::directory')->name('domicile.directory');

/*
 * A resident, at an address anybody can link to.
 *
 * The whole handle rather than the label in front of it, because the handle is
 * the thing people copy, quote and are known by — `stme.sh/profile/alice` would
 * be this server naming somebody by a part of their name that only means
 * anything while you are already here.
 */
Route::livewire('profile/{handle}', 'domicile::profile')->name('domicile.profile');
