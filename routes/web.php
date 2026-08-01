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

Route::get('/', function (Capabilities $capabilities, Identities $identities) {
    return view('streetmesh.front', [
        'identity' => $identities->forServer(),
        'front' => $capabilities->frontPage(config('streetmesh.front_page')),
    ]);
})->name('front');

Route::get('/home', function (Capabilities $capabilities, Identities $identities) {
    return view('streetmesh.home', [
        'identity' => $identities->forServer(),
        'navigation' => $capabilities->navigation(),

        // Whatever the operator arranged, or everything on offer if they have
        // not said. A server is something somebody runs rather than receives.
        'widgets' => $capabilities->widgets(config('streetmesh.home_page')),
    ]);
})->name('home');
