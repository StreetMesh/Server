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

    /** Whatever ends a track — unplugged, revoked, taken by another tab. */
    function watch(track) {
        track.addEventListener('ended', () => {
            if (!wanted.has(track.kind)) {
                return;
            }

            say(`devices: ${track.kind} ended on its own`);

            wanted.delete(track.kind);
            capture.removeTrack(track);
            onChange();
        });
    }

    function replaceCapture(granted) {
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

        tracks: () => capture.getTracks(),

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
