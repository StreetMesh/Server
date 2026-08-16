/**
 * The board, as a view of what the hub says.
 *
 * This holds no opinion about the rules. It draws the position the room reports
 * and asks for what somebody clicked; whether that is a legal move is decided
 * by the referee, and the answer comes back as new state or as a refusal. A
 * board that knew the rules would be a second implementation of them, and two
 * implementations are two chances to disagree about who won.
 *
 * So there is no chess in this file. There is a grid, a click, and a socket.
 */

import { Client } from 'colyseus.js'
import { capture, drop, lift, permit, place, refuse } from './sounds.js'

/**
 * A device somebody is holding, asked once and watched after that.
 *
 * At module scope because every board on the page would otherwise ask the same
 * question of the same browser, and the answer cannot differ between them.
 */
const HANDHELD = matchMedia('(pointer: coarse)')

/**
 * Piece artwork from Font Awesome Free, used under CC BY 4.0.
 *
 * Path data rather than the package, so that installing this experience stays
 * one step. The free set is solid-only, which decides how the two sides are
 * told apart: they share a silhouette and differ by fill and outline, the way
 * a real set does — not by outline-versus-fill, which is what the Unicode
 * chess glyphs offer and why they were the wrong tool here. A white piece
 * drawn as a white glyph on a light square is very nearly not drawn at all.
 *
 * The icons are 512 tall and between 320 and 512 wide, so each is centred with
 * a transform rather than given its own viewBox. A bound `:viewBox` would not
 * survive the HTML parser lowercasing it, and SVG treats `viewbox` as an
 * entirely different attribute — `transform` is already lowercase.
 */
const CANVAS = 512

const PIECES = {
    k: {
        name: 'king',
        width: 448,
        path: 'M224-32c17.7 0 32 14.3 32 32l0 32 32 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-32 0 0 64 153.8 0c21.1 0 38.2 17.1 38.2 38.2 0 6.4-1.6 12.7-4.7 18.3L352 384 408.2 454.3c5 6.3 7.8 14.1 7.8 22.2 0 19.6-15.9 35.5-35.5 35.5L67.5 512c-19.6 0-35.5-15.9-35.5-35.5 0-8.1 2.7-15.9 7.8-22.2L96 384 4.7 216.6C1.6 210.9 0 204.6 0 198.2 0 177.1 17.1 160 38.2 160l153.8 0 0-64-32 0c-17.7 0-32-14.3-32-32s14.3-32 32-32l32 0 0-32c0-17.7 14.3-32 32-32z',
    },
    q: {
        name: 'queen',
        width: 512,
        path: 'M256 80a48 48 0 1 0 0-96 48 48 0 1 0 0 96zM5.5 185L128 384 71.8 454.3c-5 6.3-7.8 14.1-7.8 22.2 0 19.6 15.9 35.5 35.5 35.5l312.9 0c19.6 0 35.5-15.9 35.5-35.5 0-8.1-2.7-15.9-7.8-22.2L384 384 506.5 185c3.6-5.9 5.5-12.7 5.5-19.6l0-.6c0-20.3-16.5-36.8-36.8-36.8-7.3 0-14.4 2.2-20.4 6.2l-16.9 11.3c-12.7 8.5-29.6 6.8-40.4-4l-34.1-34.1C356.1 100.1 346.2 96 336 96s-20.1 4.1-27.3 11.3l-30.1 30.1c-12.5 12.5-32.8 12.5-45.3 0l-30.1-30.1C196.1 100.1 186.2 96 176 96s-20.1 4.1-27.3 11.3l-34.1 34.1c-10.8 10.8-27.7 12.5-40.4 4L57.3 134.2c-6.1-4-13.2-6.2-20.4-6.2-20.3 0-36.8 16.5-36.8 36.8l0 .6c0 6.9 1.9 13.7 5.5 19.6z',
    },
    r: {
        name: 'rook',
        width: 384,
        path: 'M0 32L0 133.5c0 17 6.7 33.3 18.7 45.3L64 224 64 384 7.8 454.3C2.7 460.6 0 468.4 0 476.5 0 496.1 15.9 512 35.5 512l312.9 0c19.6 0 35.5-15.9 35.5-35.5 0-8.1-2.7-15.9-7.8-22.2l-56.2-70.3 0-160 45.3-45.3c12-12 18.7-28.3 18.7-45.3L384 32c0-17.7-14.3-32-32-32L320 0c-17.7 0-32 14.3-32 32l0 32-48 0 0-32c0-17.7-14.3-32-32-32L176 0c-17.7 0-32 14.3-32 32l0 32-48 0 0-32C96 14.3 81.7 0 64 0L32 0C14.3 0 0 14.3 0 32z',
    },
    b: {
        name: 'bishop',
        width: 320,
        path: 'M64 384L48.3 368.3C17.4 337.4 0 295.4 0 251.7 0 213.1 13.5 175.8 38.2 146.1L106.7 64 96 64C78.3 64 64 49.7 64 32S78.3 0 96 0L224 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-10.7 0 47.6 57.1-85.9 85.9c-9.4 9.4-9.4 24.6 0 33.9s24.6 9.4 33.9 0l82.3-82.3c18.7 27.3 28.7 59.7 28.7 93 0 43.7-17.4 85.7-48.3 116.6L256 384 312.2 454.3c5 6.3 7.8 14.1 7.8 22.2 0 19.6-15.9 35.5-35.5 35.5L35.5 512c-19.6 0-35.5-15.9-35.5-35.5 0-8.1 2.7-15.9 7.8-22.2L64 384z',
    },
    n: {
        name: 'knight',
        width: 384,
        path: 'M192-32c106 0 192 86 192 192l0 133.5c0 17-6.8 33.2-18.7 45.2L320 384 370.8 434.7c8.5 8.5 13.2 20 13.2 32 0 25-20.3 45.2-45.2 45.3L45.3 512c-25 0-45.2-20.3-45.2-45.3 0-12 4.8-23.5 13.2-32L64 384 64 349.4c0-18.7 8.2-36.4 22.3-48.6l89.7-76.8-48 0-12.1 12.1c-12.7 12.7-30 19.9-48 19.9-37.5 0-67.9-30.4-67.9-67.9l0-8.7c0-22.8 8.2-44.9 23.1-62.3L96 32 96 0c0-17.7 14.3-32 32-32l64 0zM160 72a24 24 0 1 0 0 48 24 24 0 1 0 0-48z',
    },
    p: {
        name: 'pawn',
        width: 384,
        path: 'M192-32c66.3 0 120 53.7 120 120 0 27-8.9 51.9-24 72 17.7 0 32 14.3 32 32s-14.3 32-32 32l-10.7 0 26.7 160 56.2 70.3c5 6.3 7.8 14.1 7.8 22.2 0 19.6-15.9 35.5-35.5 35.5L51.5 512c-19.6 0-35.5-15.9-35.5-35.5 0-8.1 2.7-15.9 7.8-22.2L80 384 106.7 224 96 224c-17.7 0-32-14.3-32-32s14.3-32 32-32c-15.1-20.1-24-45-24-72 0-66.3 53.7-120 120-120z',
    },
}

const FILES = 'abcdefgh'

/**
 * One piece, ready to be drawn: the path, and what centres it.
 */
function artwork(symbol) {
    const piece = PIECES[symbol.toLowerCase()]

    if (!piece) {
        return null
    }

    return {
        name: piece.name,
        path: piece.path,
        transform: `translate(${(CANVAS - piece.width) / 2} 0)`,
    }
}

/**
 * A FEN position as sixty-four squares, in the order they are drawn.
 *
 * Reading FEN is not knowing the rules — it is reading a photograph of the
 * board. Nothing here can tell whether a position is legal or whose turn it is.
 */
function squaresFrom(fen, flipped) {
    const rows = (fen || '').split(' ')[0].split('/')
    const cells = []

    rows.forEach((row, rank) => {
        let file = 0

        for (const character of row) {
            if (/\d/.test(character)) {
                file += Number(character)

                continue
            }

            cells.push({
                rank,
                file,
                name: FILES[file] + (8 - rank),
                white: character === character.toUpperCase(),
                piece: artwork(character),
            })

            file += 1
        }

        // Empty squares are drawn too, and FEN only counts them.
        for (let f = 0; f < 8; f++) {
            if (!cells.some((cell) => cell.rank === rank && cell.file === f)) {
                cells.push({ rank, file: f, name: FILES[f] + (8 - rank), white: false, piece: null })
            }
        }
    })

    cells.sort((a, b) => a.rank - b.rank || a.file - b.file)

    /*
     * Black plays from the other end. Rotating the drawing rather than the
     * position, so that a square is called the same thing on both screens.
     */
    const ordered = flipped ? [...cells].reverse() : cells

    return ordered.map((cell) => ({ ...cell, dark: (cell.rank + cell.file) % 2 === 1 }))
}

/**
 * The part of a handle this game puts on screen.
 *
 * `collegeman.stme.sh` shows as `collegeman`. A convention of this experience
 * rather than of StreetMesh: a handle is a whole address and the rest of it
 * matters everywhere else — it is what makes somebody findable, and it is the
 * part that says which server they trusted with their identity.
 *
 * At a chess table it is noise. Two people are playing and the board is small.
 * The whole address stays on the element, so it is one hover away and one
 * inspection away, and nothing that leaves this screen is shortened.
 *
 * Two players from different servers can therefore look identically named. That
 * is a real cost of the convention rather than an oversight.
 */
export function label(handle) {
    return (handle ?? '').split('.')[0]
}

/**
 * How a finished game reads, said beside the person it happened to.
 *
 * Which row gets it is the whole design: the winner's, so the sentence and the
 * name are one statement rather than two things to reconcile. It named the
 * winner when it stood on its own — "White won by resignation" — and beside
 * white's own name that is saying it twice.
 *
 * A draw belongs to nobody, so it goes on the near row, where the eye already
 * is. So does a game the venue concluded without keeping how.
 *
 * One sentence and one place that writes it, because the live board and a game
 * opened later say the same thing and used to say it in two languages — one in
 * Blade from the record, one here from the room.
 *
 * `over` is not decoration. A game in progress has no outcome yet, and a game
 * the venue never concluded has none either; without being told which of those
 * it is, an empty outcome read as "this game is over" and said so under the
 * near player's name for the whole of a live game.
 */
export function endingFor(over, side, near, outcome, winner) {
    if (!over) {
        return ''
    }

    if (!outcome) {
        return side === near ? 'this game is over' : ''
    }

    if (!winner) {
        return side === near ? `drawn by ${outcome}` : ''
    }

    return side === winner ? `won by ${outcome}` : ''
}

/**
 * A finished game, walked through.
 *
 * Reads positions the room recorded while it was being played rather than
 * working them out, so nothing here knows the rules. Deriving them would be a
 * second implementation of chess for the sake of reading a game that has
 * already been decided.
 *
 * It answers the four questions the board asks of whatever is holding it, and
 * the answers are all "no": nothing is yours to move, nothing is selected,
 * nothing is a target, and clicking does nothing.
 */
export function chessReplay({ positions, moves, seat, outcome, winner, white, black }) {
    return {
        positions,
        moves,
        seat,
        outcome,
        winner,
        players: { white, black },
        knight: artwork('n'),
        over: true,

        /** A finished game has no socket to lose. Answered so the board, which
         *  both components draw, can ask without knowing which one it is in. */
        disconnected: false,
        at: 0,

        /*
         * Nobody is to move in a finished game, and the sides are drawn the same
         * way round as they were played.
         */
        turn: '',
        playing: false,
        timer: null,

        squares: [],
        myMove: false,
        selected: null,

        isTarget() {
            return false
        },

        inCheck() {
            return false
        },

        choose() {},

        /*
         * A finished game has nothing to pick up. The board asks all of these
         * because it does not know which component it is drawing in, and the
         * answers here are the same "no" as the rest.
         */
        drag: null,

        canMoveFrom() {
            return false
        },

        clicked() {},

        startDrag() {},

        moveDrag() {},

        endDrag() {},

        endingFor(side) {
            return endingFor(this.over, side, this.near, this.outcome, this.winner)
        },

        /**
         * Whether the other chair is still empty.
         *
         * A question about the seat rather than about who is at the table this
         * minute. It used to be both — one count of the people the room could
         * see — so an opponent closing their tab turned back into somebody to
         * invite, on a board still showing their name, and the game they were
         * halfway through could no longer be resigned.
         */
        get alone() {
            return !this.players[this.far]
        },

        /** The short name this game shows, from the whole one it holds. */
        nameOf(side) {
            return label(this.players[side])
        },

        /**
         * Which side is drawn at the top, which is whoever you are not — and
         * black for somebody who played neither, the way a board is drawn when
         * nobody in particular is looking at it.
         */
        get far() {
            return this.seat === 'black' ? 'white' : 'black'
        },

        get near() {
            return this.seat === 'black' ? 'black' : 'white'
        },

        init() {
            /*
             * Where the game finished, not where it started.
             *
             * Opening a finished game on the opening position shows a board
             * nobody was looking at — the position everybody remembers is the
             * one it ended on, and it is the answer to "what happened" that the
             * heading above has just given in words.
             *
             * Replay winds back to the start; arriving does not.
             */
            this.show(this.last)
        },

        get last() {
            return Math.max(0, this.positions.length - 1)
        },

        /**
         * The move that led to the position being shown, or nothing at the
         * start — there are one more positions than moves, because the first
         * one is the board before anybody had done anything.
         */
        get playedHere() {
            return this.at > 0 ? this.moves[this.at - 1] : ''
        },

        show(index) {
            this.at = Math.min(Math.max(index, 0), this.last)
            this.squares = squaresFrom(this.positions[this.at] ?? '', seat === 'black')
        },

        /**
         * The same two sounds the live board makes, for the same reason: a move
         * you can hear is a move you noticed.
         */
        advance() {
            if (this.at >= this.last) {
                this.stop()

                return
            }

            this.show(this.at + 1)

            this.playedHere.includes('x') ? capture() : place()
        },

        play() {
            permit()

            if (this.playing) {
                this.stop()

                return
            }

            // Watching from the end means watching it again — which is the
            // ordinary case now, since a finished game opens on its last
            // position rather than its first.
            if (this.at >= this.last) {
                this.show(0)
            }

            this.playing = true
            this.timer = setInterval(() => this.advance(), 900)
        },

        stop() {
            this.playing = false
            clearInterval(this.timer)
            this.timer = null
        },

        // Nothing here outlives the page, but a timer left running after
        // navigating away is a timer still making noises.
        destroy() {
            this.stop()
        },
    }
}

export default function chessTable({ ticketUrl, settleUrl, seat, invitation, white, black }) {
    return {
        seat,
        squares: squaresFrom('rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR', seat === 'black'),
        moves: [],
        trouble: '',
        selected: null,
        over: false,
        turn: 'white',

        /** The side whose king is under attack, as the room reports it. */
        check: '',

        /** Between pointerdown and the threshold that makes it a drag. */
        holding: null,

        /** Set when a drag ends, so the click it causes is ignored once. */
        dropped: false,

        /**
         * The piece currently in somebody's hand, or null.
         *
         * Holds where it came from and where the pointer is, so the board can
         * draw it under the cursor and work out what it was dropped on. Null
         * between drags, which is also how everything drawn from it stays
         * hidden without a second flag.
         */
        drag: null,

        /**
         * Whether the socket is gone.
         *
         * Not a status message. A dropped table looks identical to a live one
         * — the pieces are where they were, the names are still under them —
         * so this is what turns the board grey and offers the way back.
         */
        disconnected: false,

        /**
         * Whether this is a device somebody is holding.
         *
         * A coarse pointer rather than a screen width or a user agent string. A
         * phone in landscape is wider than a small window on a laptop, and a
         * user agent is a thing browsers lie about on purpose — but "the
         * pointer cannot be aimed precisely" is true of a finger, false of a
         * mouse, and is the actual question.
         *
         * Set live, because it changes: an iPad reports a coarse pointer until
         * a trackpad keyboard is attached to it, and a fine one afterwards.
         */
        handheld: HANDHELD.matches,

        /** How it ended, once it has. */
        outcome: '',
        winner: '',

        /** What the invitation says once it has been sent or copied. */
        invited: '',

        /**
         * Who is playing each side, by the name their own server gave them.
         *
         * Seeded from the venue's seats and kept up to date by the room, which
         * is the difference between the two: a seat is a right to a chair and
         * survives somebody closing a tab, while the room only knows who is
         * connected. A name is never cleared once known — an opponent who has
         * dropped out for a moment is still who you are playing.
         */
        players: { white, black },

        /**
         * The position after every move, kept as they arrive.
         *
         * So that a game ending turns this board into a replay of itself
         * without the page being loaded again — the room has been sending
         * these all along and nothing here was keeping them.
         */
        positions: [],
        at: 0,
        reviewing: false,
        playing: false,
        timer: null,

        /**
         * Every move available right now, as `e2e4`, exactly as the room sent
         * it. Not derived here — see `isTarget`.
         */
        legal: [],
        room: null,
        settling: false,

        /**
         * How many moves we have already made a noise about.
         *
         * Arriving at a game in progress delivers every move at once, and
         * playing a sound for each would be a rattle rather than a board. This
         * starts at whatever was already there and only reacts to what comes
         * after.
         */
        heard: 0,

        /**
         * Whether we have seen the room's state at all yet.
         *
         * The first delivery is the game so far, however much of it there is,
         * and none of it just happened. Without this the board announced the
         * last move of a game you had only just opened — and because a browser
         * will not make a noise before you touch it, that sound sat waiting and
         * came out on the first click, sounding like a move nobody had made.
         */
        synced: false,

        /**
         * A knight, for the line saying which side you are playing.
         *
         * The same artwork the board draws, taken from the same table. A second
         * copy of a path would be a second thing to keep in step for no reason.
         */
        knight: artwork('n'),

        init() {
            HANDHELD.addEventListener('change', (pointer) => (this.handheld = pointer.matches))

            this.connect()
        },

        /**
         * Open the table, or say why not.
         *
         * Separate from `init` because it happens more than once now: a socket
         * that dropped while nobody was looking is reopened by the same route,
         * not by reloading the page and losing the board.
         */
        async connect() {
            this.disconnected = false
            this.trouble = ''

            /*
             * The next delivery is the game so far rather than anything that
             * has just happened, so it makes no noise — the same rule as the
             * first one. Coming back to four moves having been played should
             * not sound like four pieces landing at once.
             */
            this.synced = false

            /*
             * Somebody who has followed an invitation has no place here yet, so
             * there is no ticket to ask for and nothing to join. The board they
             * are looking at is a still one and the only thing on offer is a
             * chair.
             *
             * Asking anyway is how this screen used to greet them: the venue
             * answered "That visitor has no place there", and a page told a
             * stranger they were trespassing on a game they had been invited
             * to.
             */
            if (!ticketUrl) {
                return
            }

            let admitted

            try {
                const response = await fetch(ticketUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        Accept: 'application/json',
                    },
                })

                admitted = await response.json()

                if (!response.ok) {
                    throw new Error(admitted.error ?? 'The venue would not let you in.')
                }
            } catch (refused) {
                this.trouble = refused.message
                return
            }

            try {
                this.room = await new Client(admitted.hub).joinOrCreate(
                    admitted.experience.replaceAll('.', '_'),
                    { ticket: admitted.ticket, room: admitted.room },
                )
            } catch (refused) {
                this.trouble = 'Could not reach the table.'
                return
            }

            this.room.onStateChange((state) => {
                this.moves = [...state.moves]
                this.positions = [...state.positions]

                this.sound()
                this.legal = [...state.legal]
                this.turn = state.turn
                this.check = state.check
                this.outcome = state.outcome
                this.winner = state.winner

                /*
                 * Somebody who sits down while this page is open, which the
                 * venue's own seats could not have said when it rendered.
                 * Nobody is ever removed: leaving the table does not give up
                 * the chair.
                 */
                state.occupants?.forEach((who) => {
                    if (who.seat) {
                        this.players[who.seat] = who.name
                    }
                })

                this.over = state.outcome !== ''

                /*
                 * A game that has just ended becomes a record of itself, here,
                 * without the page being loaded again: the board stops taking
                 * moves, the ending is said in one line, and the whole thing
                 * can be played back.
                 *
                 * Only on the way in. Once somebody is stepping through it,
                 * later state must not drag the board back to the end under
                 * them.
                 */
                if (this.over && !this.reviewing) {
                    this.reviewing = true
                    this.show(this.last)
                } else if (!this.reviewing) {
                    this.squares = squaresFrom(state.fen, this.seat === 'black')
                }

                if (this.over) {
                    this.settle()
                }
            })

            /*
             * A refused move is answered with a noise, not a banner.
             *
             * It is the commonest thing the room ever says and the least
             * serious: a piece dragged somewhere it cannot go. A red callout
             * for that reads as a fault in the page, and it appears exactly
             * where somebody is looking at the board. A short low interval
             * says "no" in the register the rest of the board already speaks.
             *
             * The reason still goes to the console, because a refusal we did
             * not expect is worth being able to read.
             */
            this.room.onMessage('refused', ({ because }) => {
                refuse()
                console.debug('[chess] the room refused a move:', because)

                // Put the piece back down. Holding a selection that was just
                // turned away invites the same move again.
                this.selected = null
            })

            this.room.onLeave(() => {
                /*
                 * A state rather than a sentence, so the board can go quiet and
                 * the one useful action can take the place of the rest. A table
                 * that has dropped looks exactly like a live
                 * one otherwise, which is how somebody comes back from lunch
                 * and cannot work out why their move does nothing.
                 */
                this.disconnected = true
            })
        },

        /** Try the same door again. */
        reconnect() {
            this.room?.leave()
            this.room = null

            this.connect()
        },

        /**
         * Ask somebody to play.
         *
         * Through the operating system's own share sheet where there is one, so
         * the invitation goes wherever that person actually talks to their
         * opponent rather than through anything this venue would have to run.
         *
         * The sentence and the address are handed over separately because that
         * is what a share sheet expects: a target that can make a link out of a
         * URL does, and one that cannot puts the two together itself. Pasting
         * the address into the sentence as well would show it twice in most of
         * them.
         *
         * Two of them, because they are two different intentions. Somebody
         * copying a link is about to paste it somewhere themselves; somebody
         * sharing is handing it to a person. One button that chose between them
         * by asking the browser what it supported got this wrong both ways.
         *
         * Having the API is not reason enough to use it. Desktop Safari, Edge
         * and Chrome all publish `navigator.share`, and on a laptop the sheet it
         * opens is a short list of things nobody wanted — while Copy link and a
         * code on screen are both useful there. So it takes a share sheet *and*
         * a device held in the hand.
         */
        get canShare() {
            return Boolean(navigator.share) && this.handheld
        },

        /**
         * The address on its own.
         *
         * Nothing else with it. A copied link gets pasted into an address bar,
         * and a sentence in front of it makes it no longer a link.
         */
        async copyLink() {
            try {
                await navigator.clipboard.writeText(invitation)
            } catch (refused) {
                // A browser that will not write to the clipboard without a
                // gesture it recognises. Nothing useful to say, and nothing to
                // be done from here.
                return
            }

            this.said('Link copied')
        },

        /**
         * Wherever that person actually talks to their opponent.
         *
         * The sentence and the address go separately because that is what a
         * share sheet expects: a target that can make a link out of a URL does,
         * and one that cannot puts the two together itself.
         */
        async share() {
            try {
                await navigator.share({ title: 'Chess', text: `Hey, let's play Chess.`, url: invitation })
            } catch (cancelled) {
                // Dismissing the sheet throws, and is not a failure — it is
                // somebody deciding not to.
                return
            }

            this.said('Shared')
        },

        /** Said on the button itself, and taken back shortly after. */
        said(what) {
            this.invited = what

            setTimeout(() => (this.invited = ''), 2500)
        },

        /**
         * Tell the venue there is something to write down.
         *
         * The hub cannot do this itself: it decided the result and holds no key
         * to sign it with, so the record has to be written by the venue. All
         * this says is "go and look" — what happened comes from the hub when
         * the venue asks, so nothing here is trusted with the outcome.
         *
         * Once per board. Both players and every watcher will say it, which is
         * the point: it only takes one of them still having the page open, and
         * the venue ignores the rest.
         */
        async settle() {
            if (this.settling) {
                return
            }

            this.settling = true

            try {
                await fetch(settleUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        Accept: 'application/json',
                    },
                })
            } catch (unreachable) {
                // The venue keeps the game open, so the next person to open the
                // board asks again. Nothing is lost by this failing quietly.
            }
        },

        /**
         * Let go of the seat when this component goes away.
         *
         * Navigating away with `wire:navigate` never unloads the page, so
         * nothing closes the socket on its own — the old connection sits in the
         * room holding the chair, and coming back is refused as somebody
         * already sitting there. A full reload appeared to fix it because
         * unloading is the only thing that was closing anything.
         *
         * The hub no longer depends on this happening; a browser that crashes
         * cannot run it, and that has to work too.
         */
        destroy() {
            this.stop()
            this.room?.leave()
            this.room = null
        },

        get last() {
            return Math.max(0, this.positions.length - 1)
        },

        get playedHere() {
            return this.at > 0 ? this.moves[this.at - 1] : ''
        },

        endingFor(side) {
            return endingFor(this.over, side, this.near, this.outcome, this.winner)
        },

        /**
         * Whether the other chair is still empty.
         *
         * A question about the seat rather than about who is at the table this
         * minute. It used to be both — one count of the people the room could
         * see — so an opponent closing their tab turned back into somebody to
         * invite, on a board still showing their name, and the game they were
         * halfway through could no longer be resigned.
         */
        get alone() {
            return !this.players[this.far]
        },

        /**
         * Whether this is a table waiting for somebody rather than a game.
         *
         * The header shows one of three things in that one place — a way to
         * invite, whose turn it is, or the way back from a dropped socket.
         *
         * Not `asking`: the Resign button already owns that word, for asking
         * whether you meant it, and two meanings in one subtree is one too
         * many.
         */
        get waiting() {
            return Boolean(this.seat) && this.alone && !this.over
        },

        /** The short name this game shows, from the whole one it holds. */
        nameOf(side) {
            return label(this.players[side])
        },

        /**
         * Which side is drawn at the top, which is whoever you are not.
         *
         * The board turns around for black, so the far side of it is the
         * opponent — and for somebody watching, who has no side, it is black,
         * the way a board is drawn when nobody in particular is looking at it.
         */
        get far() {
            return this.seat === 'black' ? 'white' : 'black'
        },

        get near() {
            return this.seat === 'black' ? 'black' : 'white'
        },

        show(index) {
            this.at = Math.min(Math.max(index, 0), this.last)
            this.squares = squaresFrom(this.positions[this.at] ?? '', this.seat === 'black')
        },

        advance() {
            if (this.at >= this.last) {
                this.stop()

                return
            }

            this.show(this.at + 1)
            this.playedHere.includes('x') ? capture() : place()
        },

        play() {
            permit()

            if (this.playing) {
                this.stop()

                return
            }

            if (this.at >= this.last) {
                this.show(0)
            }

            this.playing = true
            this.timer = setInterval(() => this.advance(), 900)
        },

        stop() {
            this.playing = false
            clearInterval(this.timer)
            this.timer = null
        },

        /**
         * One noise per move, and only ever one of the two.
         *
         * Which one is already written in the move itself: chess notation puts
         * an `x` in a capture and nothing in an ordinary move, so the room does
         * not have to say and this does not have to work it out from the
         * position.
         *
         * Both players hear it, including the one who made it — a move you
         * cannot hear yourself make feels like it did not land.
         */
        sound() {
            const arrived = this.moves.length

            // Only the last one, however many turned up — and nothing at all
            // for the first delivery, which is the game so far rather than
            // anything that has just happened.
            if (this.synced && arrived > this.heard) {
                this.moves[arrived - 1].includes('x') ? capture() : place()
            }

            this.synced = true
            this.heard = arrived
        },

        /**
         * Giving up, which is the one ending a player decides on their own.
         *
         * The room still knows how to conclude a game by agreement — a draw is
         * a real chess ending and the referee should be able to record one —
         * but nothing on this screen offers it, so nothing here asks.
         */
        resign() {
            this.room?.send('resign')
        },

        /**
         * Whether this browser may touch the board at all.
         *
         * Watchers have no seat, and the player who is not to move has nothing
         * to do — letting them pick pieces up produces a selection that can
         * only ever be refused, which reads as the board being broken rather
         * than as it not being your turn.
         *
         * This is politeness, not enforcement. The room refuses a move from
         * the wrong seat regardless, and has to: this runs on somebody else's
         * computer.
         */
        get myMove() {
            return Boolean(this.seat) && !this.over && this.turn === this.seat
        },

        /**
         * Whether this square holds the king that is currently in trouble.
         *
         * Which side is in check comes from the room; which square that king is
         * on is read off the position we are already drawing. Neither is worked
         * out here — noticing the `+` on a move would say a check happened
         * without saying whose king it was.
         */
        inCheck(cell) {
            return this.check !== '' && cell.piece?.name === 'king' && (cell.white ? 'white' : 'black') === this.check
        },

        /**
         * Whether a square is somewhere the selected piece may go.
         *
         * A lookup in what the room published, not a calculation. Working it
         * out here would be a second implementation of chess, and two
         * implementations are two chances to disagree about who won.
         */
        isTarget(square) {
            return this.selected !== null && this.legal.includes(this.selected + square)
        },

        /**
         * Whether there is any move at all from a square.
         *
         * What makes a first click land on a piece rather than on scenery —
         * and it distinguishes your own pieces from your opponent's without
         * knowing which is which, because only the side to move has moves.
         */
        canMoveFrom(square) {
            return this.legal.some((move) => move.startsWith(square))
        },

        /**
         * A click, unless it is the tail end of a drag.
         *
         * Releasing a drag over a square makes the browser fire a click on the
         * square it started from, which would put the piece straight back down
         * again. The drag has already said what it meant.
         */
        clicked(square) {
            if (this.dropped) {
                this.dropped = false

                return
            }

            this.choose(square)
        },

        /**
         * Picking a piece up, maybe.
         *
         * Nothing is decided here. A press that never moves is a click and is
         * left entirely to `clicked`, so tapping to select behaves exactly as
         * it did — dragging is something added on top rather than a second way
         * of doing the same job.
         */
        startDrag(event, square) {
            if (!this.myMove || !this.canMoveFrom(square)) {
                return
            }

            this.holding = { square, x: event.clientX, y: event.clientY, id: event.pointerId }

            // So the rest of the gesture arrives here even when the pointer
            // leaves this square, which on a small board it does immediately.
            event.currentTarget.setPointerCapture(event.pointerId)
        },

        /**
         * Far enough to mean it.
         *
         * The threshold is what keeps a click a click: a mouse moves a pixel or
         * two between press and release, and without this every click would
         * become a drag that landed back where it started.
         */
        moveDrag(event) {
            if (!this.holding) {
                return
            }

            const far = Math.abs(event.clientX - this.holding.x) + Math.abs(event.clientY - this.holding.y) > 6

            if (!this.drag && !far) {
                return
            }

            if (!this.drag) {
                this.pickUp(this.holding.square)

                this.drag = { from: this.holding.square, cell: this.pieceOn(this.holding.square) }
            }

            this.drag = { ...this.drag, x: event.clientX, y: event.clientY }
        },

        /**
         * Letting go.
         *
         * A drag always ends with the piece out of your hand: either it moved,
         * or it went back where it came from. Nothing is left selected by
         * letting go, which is the whole of the coordination between the two
         * ways of playing — a piece that stayed selected after being put down
         * was one you could pick up again and have it fall out of your hand,
         * because picking up and putting down are the same click.
         *
         * Where it landed is asked of the document rather than tracked, because
         * the pointer is captured and every event says it is over the square it
         * started on.
         */
        endDrag(event) {
            if (!this.drag) {
                this.holding = null

                return
            }

            const from = this.drag.from
            const under = document.elementFromPoint(event.clientX, event.clientY)
            const landed = under?.closest('[data-square]')?.dataset.square

            this.drag = null
            this.holding = null
            this.dropped = true

            // Back where it started, or off the board altogether. Neither is a
            // move, and both are somebody changing their mind.
            if (!landed || landed === from) {
                this.selected = null
                drop()

                return
            }

            this.choose(landed)
        },

        /**
         * Into your hand, and only ever into it.
         *
         * `choose` toggles — the same click picks a piece up and puts it back —
         * which is right for clicking and wrong for grabbing. A piece already
         * selected and then grabbed would be released while you were still
         * holding it, and the drag that followed had nothing in hand.
         */
        pickUp(square) {
            if (this.selected === square) {
                return
            }

            this.choose(square)
        },

        /** The square being carried, artwork and side and all. */
        pieceOn(square) {
            return this.squares.find((cell) => cell.name === square) ?? null
        },

        /**
         * Two clicks: what to move, and where to.
         *
         * The second click is deliberately not validated. Clicking a square
         * that cannot be reached asks anyway and the referee says no — one code
         * path instead of two, and it cannot drift from the rules.
         */
        choose(square) {
            /*
             * Whatever else this click does, it is the interaction a browser is
             * waiting for before it will let a page make any sound at all.
             * Without it the first thing anybody would hear is their opponent's
             * move, which arrives over a socket and is not an interaction.
             */
            permit()

            if (!this.myMove) {
                return
            }

            // Putting back the piece you were holding.
            if (this.selected === square) {
                this.selected = null
                drop()

                return
            }

            // Picking up, or changing your mind about which piece. A square
            // with no moves is not a piece you can play, so it does not become
            // a selection that could only ever be refused.
            if (this.selected === null || this.canMoveFrom(square)) {
                const picking = this.canMoveFrom(square)

                this.selected = picking ? square : null

                // Only when something is actually in your hand. Clicking empty
                // board is not picking anything up, and putting a piece back
                // down is not either.
                if (picking) {
                    lift()
                }

                return
            }

            this.room?.send('move', { from: this.selected, to: square })
            this.selected = null
        },
    }
}
