{{--
    One side of the board, and who is playing it.

    Included twice, above and below, with `side` naming which end — `far` or
    `near`, answered by whatever is holding the board. Not "white" and "black",
    because which of those is at the top depends on where you are sitting.

    A piece rather than a swatch of colour. It is the same artwork the board
    draws, filled from the same two values, so a name and the pieces it belongs
    to are unmistakably the same thing — where a coloured dot was only ever a
    key to a legend.

    A side with nobody in it says so rather than showing an empty row: a table
    waiting for an opponent should look like one.

    Whose turn it is, said by the piece rather than in words. A board tells you
    that by somebody picking a piece up, and this is the nearest thing to it —
    two small hops and then a rest, so it reads as waiting rather than as
    something demanding attention.
--}}
<style>
    @keyframes chess-to-move {
        0%, 55%, 100% { transform: translateY(0); }
        18% { transform: translateY(-24%); }
        34% { transform: translateY(0); }
        44% { transform: translateY(-9%); }
    }

    .chess-piece[data-turn] {
        animation: chess-to-move 1.9s ease-in-out infinite;
    }

    /*
     * Movement is the only thing saying whose turn it is, so somebody who has
     * asked for less of it needs the words back rather than nothing at all.
     */
    .chess-to-move-said {
        display: none;
    }

    @media (prefers-reduced-motion: reduce) {
        .chess-piece[data-turn] { animation: none; }
        .chess-to-move-said { display: inline; }
    }
</style>
<div class="flex w-full max-w-[26rem] items-center gap-2 lg:max-w-[34rem] xl:max-w-[38rem]">
    <svg
        viewBox="0 0 512 512"
        class="chess-piece size-5 shrink-0 overflow-visible stroke-white stroke-[66] [paint-order:stroke]"
        x-bind:data-side="{{ $side }}"
        x-bind:data-turn="!over && turn === {{ $side }} ? 'yes' : null"
        aria-hidden="true"
    >
        <path x-bind:d="knight.path" x-bind:transform="knight.transform"></path>
    </svg>

    {{--
        The label rather than the whole address — a convention of this game, not
        of StreetMesh. The whole handle stays on the element, so it is one hover
        away and nothing that leaves this screen is shortened.
    --}}
    <flux:text class="truncate text-sm font-semibold">
        <span
            x-show="players[{{ $side }}]"
            x-text="nameOf({{ $side }})"
            x-bind:title="players[{{ $side }}]"
        ></span>
        <span x-show="!players[{{ $side }}]" x-cloak>{{ __('Waiting for a player') }}</span>
    </flux:text>

    {{-- Only for somebody who has asked not to be shown the movement. --}}
    <flux:text
        x-show="!over && turn === {{ $side }}"
        x-cloak
        class="chess-to-move-said text-xs"
    >{{ __('to move') }}</flux:text>

    {{--
        How it ended, beside the person it happened to.

        On the winner's row, so the sentence and the name are one statement. It
        stood on its own above the board and had to name the winner to make
        sense — "White won by resignation" — which beside white's own name is
        saying it twice.

        Font Awesome Free, CC BY 4.0. A chequered flag rather than a plain one,
        which is what the end of a game looks like; Flux ships only the plain
        one, so it comes from the same set as the pieces.

        Copied out of the package, never typed. Transcribed by hand it comes out
        eleven characters short — twice now — and what goes missing is the
        subpaths that cut the checks out, so it fills as one solid shape. It is
        invisible in a diff and unmissable on the screen.
    --}}
    <span x-show="endingFor({{ $side }})" x-cloak class="flex items-center gap-1.5">
        <svg viewBox="0 0 448 512" class="size-3.5 shrink-0 fill-current opacity-60" aria-hidden="true">
            <path d="M32 0C49.7 0 64 14.3 64 32l0 16 69-17.2c38.1-9.5 78.3-5.1 113.5 12.5 46.3 23.2 100.8 23.2 147.1 0l9.6-4.8C423.8 28.1 448 43.1 448 66.1l0 279.7c0 13.3-8.3 25.3-20.8 30l-34.7 13c-46.2 17.3-97.6 14.6-141.7-7.4-37.9-19-81.4-23.7-122.5-13.4L64 384 64 480c0 17.7-14.3 32-32 32S0 497.7 0 480L0 32C0 14.3 14.3 0 32 0zM64 187.1l64-13.9 0 65.5-64 13.9 0 65.5 48.8-12.2c5.1-1.3 10.1-2.4 15.2-3.3l0-63.9 38.9-8.4c8.3-1.8 16.7-2.5 25.1-2.1l0-64c13.6 .4 27.2 2.6 40.4 6.4l23.6 6.9 0 66.7-41.7-12.3c-7.3-2.1-14.8-3.4-22.3-3.8l0 71.4c21.8 1.9 43.3 6.7 64 14.4l0-69.8 22.7 6.7c13.5 4 27.3 6.4 41.3 7.4l0-64.2c-7.8-.8-15.6-2.3-23.2-4.5l-40.8-12 0-62c-13-3.8-25.8-8.8-38.2-15-8.2-4.1-16.9-7-25.8-8.8l0 72.4c-13-.4-26 .8-38.7 3.6l-25.3 5.5 0-75.2-64 16 0 73.1zM320 335.7c16.8 1.5 33.9-.7 50-6.8l14-5.2 0-71.7-7.9 1.8c-18.4 4.3-37.3 5.7-56.1 4.5l0 77.4zm64-149.4l0-70.8c-20.9 6.1-42.4 9.1-64 9.1l0 69.4c13.9 1.4 28 .5 41.7-2.6l22.3-5.2z"></path>
        </svg>

        <flux:text class="text-sm" x-text="endingFor({{ $side }})"></flux:text>
    </span>

    @if ($side === 'near')
        {{--
            Giving up, beside your own name — it is the one thing you can do
            that is not a move, and it belongs with the side it would give up.

            Not until there is somebody to resign to: a table with one person at
            it is somebody waiting, and the room refuses it anyway.

            It asks before it acts, and cannot be taken back.
        --}}
        <span
            x-show="seat && !over && !alone && !disconnected"
            x-cloak
            class="ms-auto"
            x-data="{ asking: false }"
            @click.outside="asking = false"
        >
            <flux:button
                size="sm"
                variant="ghost"
                @click="asking ? (asking = false, resign()) : asking = true"
                x-bind:class="asking ? 'text-rose-600 dark:text-rose-400' : ''"
            >
                <span x-text="asking ? '{{ __('Really resign?') }}' : '{{ __('Resign') }}'"></span>
            </flux:button>
        </span>
    @endif
</div>
