<?php

use Illuminate\Support\Facades\Route;
use StreetMesh\Protocol\Laravel\Http\ClientController;
use StreetMesh\Protocol\Laravel\Http\ConsentController;
use StreetMesh\Protocol\Laravel\Http\IdentityController;
use StreetMesh\Protocol\Laravel\Http\PermissionController;
use StreetMesh\Protocol\Laravel\Http\PermissionMetadataController;
use StreetMesh\Protocol\Laravel\Plc\DirectoryController;
use StreetMesh\Protocol\Laravel\Http\RepoController;

/*
 * Everything a stranger may ask without introducing themselves.
 *
 * Deliberately outside the web middleware group: the caller is another server
 * rather than a browser, so there is no session to start and no CSRF token to
 * check, and putting these behind browser protections only breaks them.
 */
Route::middleware('streetmesh')->group(function (): void {
    Route::get('.well-known/did.json', [IdentityController::class, 'document'])
        ->name('streetmesh.did');

    Route::get('.well-known/atproto-did', [IdentityController::class, 'handle'])
        ->name('streetmesh.handle');

    /*
     * How this server introduces itself when it is the one asking.
     *
     * Not under .well-known, because its URL is not a convention to be looked
     * up — it *is* this venue's identifier, and it travels inside every request
     * for permission. A domicile fetches it because it was handed the address,
     * not because it knows where to look.
     */
    Route::get('client-metadata.json', [ClientController::class, 'metadata'])
        ->name('streetmesh.client');

    Route::get('jwks.json', [ClientController::class, 'keys'])
        ->name('streetmesh.jwks');

    /*
     * And how it answers when it is the one being asked.
     *
     * These two say what this server will do before anybody tries it, which is
     * what lets a venue that has never heard of us decide whether we are worth
     * talking to at all.
     */
    Route::get('.well-known/oauth-protected-resource', [PermissionMetadataController::class, 'resource'])
        ->name('streetmesh.oauth.resource');

    Route::get('.well-known/oauth-authorization-server', [PermissionMetadataController::class, 'server'])
        ->name('streetmesh.oauth.server');

    /*
     * The two a venue posts to. No session and no CSRF token, for the same
     * reason as everything else in this group: the caller is a server, and it
     * authenticates by signing rather than by holding a cookie.
     */
    Route::post('oauth/par', [PermissionController::class, 'push'])
        ->name('streetmesh.oauth.par');

    Route::post('oauth/token', [PermissionController::class, 'token'])
        ->name('streetmesh.oauth.token');

    /*
     * And what all of it was for: somebody else writing a record into a
     * resident's own store, with permission that resident gave.
     *
     * Under `xrpc/` and named as ATProtocol names it, because this is their
     * method rather than one of ours — a client that already knows how to write
     * a record to a PDS should not have to learn a second way to write one
     * here.
     */
    Route::post('xrpc/com.atproto.repo.createRecord', [RepoController::class, 'create'])
        ->name('streetmesh.repo.create');

    /*
     * A PLC directory, when this server keeps one.
     *
     * Here rather than in the group below, and that is not a detail: a
     * directory is asked by other servers and by this one talking to itself,
     * neither of which has a session. Behind `auth` it answered 401 to
     * everybody, including its own resident registering.
     *
     * Under a prefix rather than at a host of its own, which costs nothing: the
     * client builds every URL as the configured directory plus the identifier,
     * so a path is as good as a hostname. Point `STREETMESH_PLC_DIRECTORY` at
     * `<this server>/plc` and a developer needs no second host, no container
     * and no daemon to remember.
     *
     * Registered whether or not this server keeps one, and refused per request
     * inside. A config read while routes are being registered gets cached into
     * the route table, and then turning the setting on appears to do nothing.
     *
     * `_health` first, or it is read as somebody's identifier.
     */
    Route::get('plc/_health', [DirectoryController::class, 'health'])
        ->name('streetmesh.plc.health');

    Route::get('plc/{did}/log/audit', [DirectoryController::class, 'log'])
        ->name('streetmesh.plc.log');

    Route::get('plc/{did}', [DirectoryController::class, 'resolve'])
        ->name('streetmesh.plc.resolve');

    Route::post('plc/{did}', [DirectoryController::class, 'submit'])
        ->name('streetmesh.plc.submit');
});

/*
 * The one part of this a person sees, and so the one part that needs a browser.
 *
 * Inside the web group, and behind a login, because this is somebody deciding
 * about their own records — the session is the whole point here rather than an
 * obstacle, which is the opposite of every route above.
 */
Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('oauth/authorize', [ConsentController::class, 'show'])
        ->name('streetmesh.oauth.authorize');

    Route::post('oauth/authorize', [ConsentController::class, 'approve'])
        ->name('streetmesh.oauth.approve');
});

/*
 * There is deliberately no route here for the front page.
 *
 * The rule this package establishes — that nothing claims the root, because
 * Laravel replaces a route sharing a path rather than complaining — applies to
 * this package too, and a redirect registered here would either overwrite the
 * application's own root or be overwritten by it, silently, depending on boot
 * order.
 *
 * An application that wants its front door to lead somewhere says so itself:
 *
 *     Route::redirect('/', '/'.config('streetmesh.mount.domicile'));
 *
 * or, to follow whatever is installed rather than naming one:
 *
 *     Route::get('/', fn () => redirect()->route(
 *         app(Capabilities::class)->homeRoute(config('streetmesh.home'))
 *     ));
 */
