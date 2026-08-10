<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * Where a guest is sent, on a server that may have nowhere to sign in.
         *
         * Laravel sends them to `route('login')`. A venue with no domicile has
         * no such route — the domicile package brings it, and a server holding
         * no accounts has nothing to bring it for — so every page behind `auth`
         * answered a signed-out visitor with "Route [login] not defined", which
         * is a 500 about somebody clicking a link.
         *
         * The front page instead, which every server has. Not the venue's door:
         * going through it makes somebody a visitor, and a visitor is still not
         * a resident, so they would be walked through an entire handshake only
         * to be turned away by this same middleware at the end of it.
         */
        $middleware->redirectGuestsTo(
            fn (): string => Route::has('login') ? route('login') : url('/'),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
