/**
 * Which rooms this venue's hub serves.
 *
 * The list belongs here rather than in the hub, because a venue is the only
 * thing that knows what it has installed — and a hub that went looking would
 * have to assume a directory layout belonging to somebody else's application.
 *
 * One line per installed experience, for now. That is a deliberate placeholder
 * of the same kind as `resources/js/app.js`: installing an experience should be
 * one step, and today it is three — require the package, name it here, and name
 * its JavaScript there. Views and styles are already discovered rather than
 * listed, and this should end up the same way.
 */

import chess from './packages/laravel-chess/hub/src/room.ts'

export default [chess]
