<?php

use Illuminate\Support\Facades\Route;

use StreetMesh\Protocol\Laravel\Capabilities\Capabilities;

/*
 * The front door leads to whichever capability this server is mostly for.
 *
 * Written here rather than supplied by a package, because a package taking the
 * root would win or lose on boot order and nobody would have decided it. This
 * is the application deciding.
 */
Route::get('/', function (Capabilities $capabilities) {
    $route = $capabilities->homeRoute(config('streetmesh.home'));

    return $route === null ? view('welcome') : redirect()->route($route);
});
