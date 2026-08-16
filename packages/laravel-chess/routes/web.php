<?php

use Illuminate\Support\Facades\Route;
use StreetMesh\Chess\Http\SettleController;
use StreetMesh\Chess\Http\SitController;

/*
 * The games, for somebody who has not come to play.
 *
 * A separate address rather than the lobby with its door taken off, because
 * the two are different invitations: `chess.lobby` is where you go to play and
 * asks you to arrive first, and this is where you go to look and asks nothing
 * at all. One screen serves both — everything on it that acts is guarded
 * already, so a stranger sees the games and can open any of them.
 *
 * Before the table below it, and that is not a matter of taste. `{key}` would
 * match the word `watch` and answer with a game that does not exist.
 */
Route::livewire('experiences/chess/watch', 'chess::lobby')->name('chess.watch');

/*
 * A table is readable by anybody.
 *
 * Somebody following an invitation has not been here before and has no reason
 * to have. Sending them to a door first means asking a stranger to name a
 * server they may never have thought about, to look at a thing they were
 * invited to — so the game is a page, and sitting down at it is the moment that
 * asks anything of them.
 *
 * Nothing is given away by this. A board is a position and two names, and both
 * players can see it; what is behind the door is *doing* anything.
 */
Route::livewire('experiences/chess/{key}', 'chess::table')->name('chess.table');

/*
 * Everything that acts is behind it, because acting means arriving with a name
 * issued somewhere else first.
 */
Route::middleware('visitor')->group(function (): void {
    /*
     * Under the menu it is reached from. An experience is something you find at
     * a venue rather than a thing the venue is, and the address says so.
     */
    Route::livewire('experiences/chess', 'chess::lobby')->name('chess.lobby');

    /*
     * Taking a chair.
     *
     * A GET on purpose. Somebody who is not through the door yet is sent there
     * by the middleware, which remembers where they were going — and coming
     * back to a remembered address only works if it was one that could be
     * followed. It is not a link anybody types; it is where "Sit to play"
     * points.
     */
    Route::get('experiences/chess/{key}/sit', SitController::class)->name('chess.sit');

    /*
     * "The game is over, go and look."
     *
     * A browser can ask; it cannot say what happened. The answer comes from the
     * hub, and a game still being played answers nothing — so calling this
     * early, often, or for somebody else's table achieves nothing at all.
     */
    Route::post('experiences/chess/{key}/settle', SettleController::class)->name('chess.settle');
});
