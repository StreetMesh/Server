<?php

use Illuminate\Support\Facades\Route;
use StreetMesh\Protocol\Laravel\Capabilities\Capabilities;
use StreetMesh\Protocol\Laravel\Identity\Identities;

/*
 * The two surfaces a server owns, as distinct from the screens its capabilities
 * own.
 *
 * A capability has a directory of residents, or a menu of experiences, or a
 * browser for somebody's records — screens with names nothing else wants. These
 * two are different: there is one root and one home page however many
 * capabilities are installed, so neither can belong to a package.
 */

// The front page: what anybody sees, signed in or not.
Route::get('/', function (Capabilities $capabilities, Identities $identities) {
    return view('welcome', [
        'identity' => $identities->forServer(),
        'front' => $capabilities->frontPage(config('streetmesh.front_page')),
        'action' => $capabilities->frontAction(config('streetmesh.front_page')),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // The home page: what somebody signed in sees. Keeps the starter kit's
    // route name so its own links and redirects continue to work.
    Route::get('dashboard', function (Capabilities $capabilities) {
        return view('dashboard', [
            'widgets' => $capabilities->widgets(config('streetmesh.home_page')),
        ]);
    })->name('dashboard');
});

require __DIR__.'/settings.php';
