/**
 * Five sounds: a piece picked up, put back, set down, taken — and refused.
 *
 * Made rather than loaded. Four short noises are a few lines of arithmetic and
 * no files, which keeps installing this experience one step — a package that
 * needed audio assets served would need the host to know about them, and a
 * package that pulled them from somewhere else would need the host to be
 * online.
 *
 * They are deliberately unalike rather than louder and quieter versions of each
 * other. Setting a piece down is wood on wood: low, short, over immediately.
 * Taking one has an edge on it — the same knock with something scraping across
 * the top — because it is the move you want to notice from across the room.
 * Lifting one is the lightest and the only one that is not an impact: nothing
 * has landed yet. Putting it back is that sound falling instead of rising —
 * the same gesture undone, and quieter than either, because changing your mind
 * is not an event.
 */

/**
 * One context, made on first use.
 *
 * A browser refuses to start audio before somebody has interacted with the
 * page, so building this at load would produce a context that is permanently
 * suspended. Made on the first sound instead, which by definition follows
 * something happening.
 */
let audio = null

function context() {
    audio ??= new (window.AudioContext ?? window.webkitAudioContext)()

    // A tab that has been backgrounded comes back suspended, and a suspended
    // context plays nothing while reporting no error at all.
    if (audio.state === 'suspended') {
        void audio.resume()
    }

    return audio
}

/**
 * Make a sound, if making it now still means what it meant.
 *
 * A sound scheduled into a suspended context is not dropped — it waits, and
 * arrives whenever the browser lets audio start, which is the next time
 * somebody clicks something. That produced a knock on the first click of a
 * refreshed game: not a queue we built, but one the audio clock kept.
 *
 * So a sound the room caused is dropped if it cannot be heard immediately. It
 * described a move that has already happened, and playing it late describes it
 * wrongly.
 *
 * A sound *you* caused waits, because starting the audio is exactly what your
 * click is for. `resume()` does not finish within the gesture that permits it,
 * so insisting is the difference between a first click that is silent and one
 * that answers. Two lines apart, and the reason the first click after a refresh
 * made no sound at all.
 */
function play(build, insist = false) {
    const ctx = context()

    if (ctx.state === 'running') {
        build(ctx, ctx.currentTime)

        return
    }

    if (!insist) {
        return
    }

    void ctx.resume().then(() => {
        if (ctx.state === 'running') {
            build(ctx, ctx.currentTime)
        }
    })
}

/**
 * Whether this browser will let us make a noise yet.
 *
 * Called on the first click anywhere on the board, because that click is the
 * interaction the autoplay rules are waiting for. Without it the first sound
 * anybody hears is their opponent's move, which arrives over a socket and is
 * not an interaction — so it would be silent.
 */
export function permit() {
    context()
}

/**
 * A short burst of noise, shaped.
 *
 * The tick of a lift, the tap in front of a place, the scrape of a capture.
 * Generated per sound rather than kept, because it is a couple of thousand
 * random numbers and holding on to them saves nothing worth measuring.
 */
function noise(ctx, seconds) {
    const samples = Math.floor(ctx.sampleRate * seconds)
    const buffer = ctx.createBuffer(1, samples, ctx.sampleRate)
    const channel = buffer.getChannelData(0)

    for (let i = 0; i < samples; i++) {
        // Fading as it goes, so it reads as an impact rather than as static.
        channel[i] = (Math.random() * 2 - 1) * (1 - i / samples)
    }

    const source = ctx.createBufferSource()
    source.buffer = buffer

    return source
}

/**
 * A piece picked up off the board.
 *
 * The odd one out, and deliberately: the other two are things landing, and this
 * is a thing leaving. High, quiet and almost instant — you have not done
 * anything yet, and a sound that announced you had would be lying about a move
 * you can still change your mind about.
 */
export function lift() {
    play((ctx, at) => {
        const tick = noise(ctx, 0.012)

        // High and narrow, so it reads as a fingernail catching rather than as
        // anything with weight behind it.
        const edge = ctx.createBiquadFilter()
        edge.type = 'highpass'
        edge.frequency.value = 2600

        const shape = ctx.createGain()
        shape.gain.setValueAtTime(0.07, at)
        shape.gain.exponentialRampToValueAtTime(0.0006, at + 0.03)

        tick.connect(edge).connect(shape).connect(ctx.destination)
        tick.start(at)

        // A hint of pitch under it so it is a sound rather than a click, an octave
        // and a half above where a piece lands.
        const body = ctx.createOscillator()
        body.type = 'sine'
        body.frequency.setValueAtTime(560, at)

        const bodyShape = ctx.createGain()
        bodyShape.gain.setValueAtTime(0.05, at)
        bodyShape.gain.exponentialRampToValueAtTime(0.0006, at + 0.05)

        body.connect(bodyShape).connect(ctx.destination)
        body.start(at)
        body.stop(at + 0.06)
    }, true)
}

/**
 * A piece put back where it was.
 *
 * `lift` upside down: the same tick with the pitch falling instead of holding,
 * and quieter than any of the others. Changing your mind is not an event, and
 * it should not sound like one — but silence would leave you wondering whether
 * the board had heard you.
 */
export function drop() {
    play((ctx, at) => {
        const tick = noise(ctx, 0.012)

        const edge = ctx.createBiquadFilter()
        edge.type = 'highpass'
        edge.frequency.value = 1800

        const shape = ctx.createGain()
        shape.gain.setValueAtTime(0.045, at)
        shape.gain.exponentialRampToValueAtTime(0.0006, at + 0.03)

        tick.connect(edge).connect(shape).connect(ctx.destination)
        tick.start(at)

        // Falling where a lift holds steady, which is the whole of the difference.
        const body = ctx.createOscillator()
        body.type = 'sine'
        body.frequency.setValueAtTime(430, at)
        body.frequency.exponentialRampToValueAtTime(260, at + 0.06)

        const bodyShape = ctx.createGain()
        bodyShape.gain.setValueAtTime(0.04, at)
        bodyShape.gain.exponentialRampToValueAtTime(0.0006, at + 0.07)

        body.connect(bodyShape).connect(ctx.destination)
        body.start(at)
        body.stop(at + 0.08)
    }, true)
}

/**
 * Wood on wood. Low, short, and over before you have thought about it.
 */
export function place() {
    play((ctx, at) => {
        const body = ctx.createOscillator()
        body.type = 'triangle'
        body.frequency.setValueAtTime(190, at)
        body.frequency.exponentialRampToValueAtTime(70, at + 0.09)

        const shape = ctx.createGain()
        shape.gain.setValueAtTime(0.28, at)
        shape.gain.exponentialRampToValueAtTime(0.0008, at + 0.11)

        body.connect(shape).connect(ctx.destination)
        body.start(at)
        body.stop(at + 0.12)

        // The knock itself, so it lands rather than swells.
        const tap = noise(ctx, 0.02)
        const edge = ctx.createBiquadFilter()
        edge.type = 'lowpass'
        edge.frequency.value = 2200

        const tapShape = ctx.createGain()
        tapShape.gain.setValueAtTime(0.12, at)
        tapShape.gain.exponentialRampToValueAtTime(0.0008, at + 0.03)

        tap.connect(edge).connect(tapShape).connect(ctx.destination)
        tap.start(at)
    })
}

/**
 * A piece taken: the same knock with something dragged across it.
 *
 * Not a louder `place`. It is the one move worth noticing without looking, so
 * it is a different shape rather than a different volume.
 */
export function capture() {
    play((ctx, at) => {
        const scrape = noise(ctx, 0.16)

        // Narrow and falling, which is what makes it read as something sliding
        // rather than as a second knock.
        const edge = ctx.createBiquadFilter()
        edge.type = 'bandpass'
        edge.Q.value = 1.4
        edge.frequency.setValueAtTime(2600, at)
        edge.frequency.exponentialRampToValueAtTime(700, at + 0.15)

        const shape = ctx.createGain()
        shape.gain.setValueAtTime(0.16, at)
        shape.gain.exponentialRampToValueAtTime(0.0008, at + 0.17)

        scrape.connect(edge).connect(shape).connect(ctx.destination)
        scrape.start(at)

        // Landing underneath it, a little lower than a plain move so the two are
        // told apart even where the scrape is lost to a bad speaker.
        const body = ctx.createOscillator()
        body.type = 'triangle'
        body.frequency.setValueAtTime(150, at + 0.02)
        body.frequency.exponentialRampToValueAtTime(60, at + 0.13)

        const bodyShape = ctx.createGain()
        bodyShape.gain.setValueAtTime(0.001, at)
        bodyShape.gain.linearRampToValueAtTime(0.3, at + 0.03)
        bodyShape.gain.exponentialRampToValueAtTime(0.0008, at + 0.15)

        body.connect(bodyShape).connect(ctx.destination)
        body.start(at)
        body.stop(at + 0.16)
    })
}

/**
 * No.
 *
 * Two low notes a semitone apart, sounded together and cut off short. A
 * flattened second is the interval that has meant "wrong" for four hundred
 * years, and it does not have to be loud to land — this answers a move that
 * was never going to happen, not a fault.
 *
 * It insists, because it is the only answer to something the player did: a
 * refusal nobody hears is a board that ignored them.
 */
export function refuse() {
    play((ctx, at) => {
        const shape = ctx.createGain()
        shape.gain.setValueAtTime(0.05, at)
        shape.gain.exponentialRampToValueAtTime(0.0006, at + 0.16)
        shape.connect(ctx.destination)

        for (const hertz of [196, 185]) {
            const tone = ctx.createOscillator()
            tone.type = 'triangle'
            tone.frequency.setValueAtTime(hertz, at)

            tone.connect(shape)
            tone.start(at)
            tone.stop(at + 0.17)
        }
    }, true)
}
