/*
 * What the browser gets, beyond what Livewire and Flux bring themselves.
 *
 * Packages that ship a screen with behaviour are imported here, by name. That
 * is the current answer to "how does an installed package get its JavaScript
 * into the page", and it is a deliberate placeholder: the host naming each one
 * means installing an experience is two steps rather than one, which is worse
 * than how views and styles work.
 *
 * Views resolve through a Livewire namespace and styles through a `@source`
 * glob, both discovered rather than listed. JavaScript has no equivalent yet.
 */

import chessTable from '../../packages/laravel-chess/resources/js/table.js'

/*
 * Registered on Alpine rather than exported, because a Blade template asks for
 * a component by name — `x-data="chessTable(...)"` — and has no import of its
 * own.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('chessTable', chessTable)
})
