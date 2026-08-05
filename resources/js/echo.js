/**
 * The socket this application listens on.
 *
 * Not the same socket a board plays over. A board is *in* a room and talks to
 * the hub directly, which is why a move lands instantly; this is for everything
 * that is not in a room and would otherwise have to keep asking — a menu of
 * tables, a count of who is at one.
 *
 * Reverb, and reached over TLS rather than the plain port it binds to. Pages
 * here are served over https and a browser refuses an insecure socket from a
 * secure page — the same rule that made the chess board unreachable in Safari
 * until the hub went behind Herd's certificate.
 */

import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    wssPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',

    // Only the socket. Falling back to long polling would hide a broken
    // configuration behind something that almost works, and this is a
    // development machine where finding that out is the point.
    enabledTransports: ['ws', 'wss'],
})
