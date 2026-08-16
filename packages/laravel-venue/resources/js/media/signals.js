import { say, trouble } from './log.js'

/**
 * The box each side leaves the other notes in.
 *
 * Two of them, at most, per connection: an offer and an answer, plus a handful
 * of addresses. Once two browsers have found each other there is nothing
 * further to say, and the audio and video go straight between them without
 * touching this or anything else on the server.
 *
 * It keeps looking anyway, slowly, because the other side can always reload and
 * want to start again.
 *
 * Polled, and that is the part of this worth replacing. The number of boxes to
 * watch grows with the size of the party, and the server this runs on has a
 * broadcast channel already configured.
 */
export default function signals({ url, session, csrf, onNotes, pace }) {
    let waiting = null
    let stopped = false
    let complaining = false

    /**
     * Notes go out in the order they were made.
     *
     * `send` is fire-and-forget and this is a `fetch`, so without a queue every
     * note races every other — and the offer, having by far the biggest body,
     * loses. Its own ICE candidates arrived at the far side first, where they
     * describe a session description that is not there yet: `addIceCandidate`
     * throws, the candidates are gone, and the handshake never finishes.
     *
     * A chain rather than a lock. Nothing here needs to be parallel — this is a
     * handful of messages that stop for good once two browsers have found each
     * other — and order is the only property that matters.
     */
    let sending = Promise.resolve()

    /** Say it once, then stay quiet until it works again. */
    function report(what, error) {
        if (!complaining) {
            complaining = true
            trouble(what, error)
        }
    }

    async function collect() {
        const response = await fetch(`${url}?as=${encodeURIComponent(session)}`, {
            headers: { Accept: 'application/json' },
        })

        if (!response.ok) {
            throw new Error(`the venue answered ${response.status}`)
        }

        return (await response.json()).signals ?? []
    }

    async function look() {
        clearTimeout(waiting)

        try {
            const notes = await collect()

            complaining = false

            await onNotes(notes)
        } catch (error) {
            report('could not collect what was left for us', error)
        }

        if (!stopped) {
            waiting = setTimeout(look, pace())
        }
    }

    async function deliver(to, note) {
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ to, from: session, data: note }),
            })

            /*
             * A refused note is silence, not an error.
             *
             * `fetch` only rejects when the request never happened, so a 419 or
             * a 500 came back here as success — every offer was dropped on the
             * floor and both sides sat waiting for a handshake neither could
             * see failing.
             */
            if (!response.ok) {
                throw new Error(`the venue answered ${response.status}`)
            }

            say(`left a ${note.description ? note.description.type : 'candidate'} for ${to}`)
        } catch (error) {
            report('could not leave a note', error)
        }
    }

    return {
        /**
         * Leave a note, after every note left before it.
         *
         * Order is the whole point — see `sending`.
         */
        post(to, note) {
            sending = sending.then(() => deliver(to, note))

            return sending
        },

        start() {
            say(`signalling as ${session}`)
            stopped = false
            void look()
        },

        /** Somebody arrived. Don't sit on the slow cadence waiting to notice. */
        hurry() {
            if (!stopped) {
                void look()
            }
        },

        stop() {
            stopped = true
            clearTimeout(waiting)
        },
    }
}
