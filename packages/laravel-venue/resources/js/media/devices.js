import { say, trouble } from './log.js';

/**
 * The microphone and camera on this machine.
 *
 * One capture, always. Asking the browser for a camera while it is already
 * holding a microphone is not additive on WebKit — the second request ends the
 * tracks from the first, and a sender left holding a dead track goes quiet with
 * the button still lit and nothing to say why. So adding a kind re-asks for
 * everything wanted in a single call and hands back a whole new set of tracks.
 *
 * Dropping a kind is the opposite: stop that track and leave the rest alone,
 * which costs no second permission prompt and disturbs nothing.
 */
export default function devices({ onChange }) {
    const wanted = new Set();

    let capture = new MediaStream();

    /**
     * These listeners live exactly as long as the capture they came from.
     *
     * One controller per generation, aborted before the tracks it belongs to
     * are stopped. Without it, a superseded track could still speak: the
     * handler below asks whether its *kind* is wanted, not whether the track is
     * still ours, so an `ended` arriving late from a track we had already
     * replaced would be read as the current one dying.
     *
     * That is not hypothetical. Asking WebKit for a camera while it holds a
     * microphone ends the microphone's track, and the event for it arrives
     * while the replacement is already installed.
     */
    let generation = new AbortController();

    /** Whatever ends a track — unplugged, revoked, taken by another tab. */
    function watch(track) {
        track.addEventListener(
            'ended',
            () => {
                /*
                 * Belt as well as braces. Aborting and the event the platform
                 * had already queued are a race, and this side of it costs one
                 * expression.
                 */
                if (!capture.getTracks().includes(track)) {
                    return;
                }

                if (!wanted.has(track.kind)) {
                    return;
                }

                say(`devices: ${track.kind} ended on its own`);

                wanted.delete(track.kind);
                capture.removeTrack(track);
                onChange();
            },
            { signal: generation.signal },
        );
    }

    function replaceCapture(granted) {
        /* Before stopping anything, so nothing we are about to discard is heard
           from afterwards. */
        generation.abort();
        generation = new AbortController();

        for (const track of capture.getTracks()) {
            track.stop();
        }

        capture = granted;

        for (const track of capture.getTracks()) {
            watch(track);
        }
    }

    async function acquire(kinds) {
        const constraints = Object.fromEntries([...kinds].map((kind) => [kind, true]));

        return navigator.mediaDevices.getUserMedia(constraints);
    }

    return {
        stream: () => capture,

        holds: (kind) => wanted.has(kind),

        /**
         * What may be sent to somebody else.
         *
         * Filtered rather than handed over whole, because `wanted` and
         * `capture` can disagree and every disagreement in this direction is a
         * microphone nobody knows is on. A kind can leave `wanted` while a live
         * track of that kind stays in `capture` — a second `getUserMedia` that
         * ends the first one's tracks, a button pressed twice before the prompt
         * is answered, an acquisition that failed after another had already
         * succeeded — and this is the last thing standing between any of that
         * and the whole party hearing somebody who believes they are muted.
         *
         * Loud when it bites, because a track being masked here means something
         * upstairs is wrong and the mask is treatment rather than cure: it
         * stops anybody hearing you, and it does not close the microphone. The
         * light on the machine stays on.
         */
        tracks: () => {
            const offered = capture.getTracks();
            const sendable = offered.filter(
                (track) => wanted.has(track.kind) && track.readyState === 'live',
            );

            if (sendable.length !== offered.length) {
                trouble('devices: holding back a track nobody asked for', {
                    wanted: [...wanted],
                    held: offered.map((track) => `${track.kind}:${track.readyState}`),
                });
            }

            return sendable;
        },

        /**
         * Start capturing one more kind.
         *
         * Reports whether it got it, rather than throwing: a person declining a
         * camera prompt is an ordinary answer, not an exceptional one.
         */
        async add(kind) {
            const before = new Set(wanted);

            wanted.add(kind);

            try {
                replaceCapture(await acquire(wanted));
            } catch (error) {
                trouble(`devices: could not get ${kind}`, error);

                /*
                 * The failed request may have taken what we already held with
                 * it, so what is left is worth asking for again.
                 */
                if (before.size) {
                    try {
                        replaceCapture(await acquire(before));
                    } catch (second) {
                        trouble('devices: lost what we already had', second);
                        wanted.clear();
                    }
                }

                wanted.delete(kind);
                onChange();

                return false;
            }

            say(`devices: holding ${[...wanted].join(', ')}`);
            onChange();

            return true;
        },

        drop(kind) {
            wanted.delete(kind);

            for (const track of capture.getTracks()) {
                if (track.kind === kind) {
                    track.stop();
                    capture.removeTrack(track);
                }
            }

            say(`devices: holding ${[...wanted].join(', ') || 'nothing'}`);
            onChange();
        },

        releaseAll() {
            wanted.clear();
            replaceCapture(new MediaStream());
        },
    };
}
