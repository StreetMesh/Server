{{--
    The board, drawn from whatever is holding it.

    Included by the live table and by the replay of a finished one, which read
    entirely different things — a websocket in one case and a list of recorded
    positions in the other. Neither is mentioned here. All this needs is a
    `squares` array and something to answer `myMove`, `selected`, `isTarget`
    and `choose`, which the replay answers with "no", "nothing", "no" and
    "nothing happens".

    One copy because two copies drift. The account menu was two copies of one
    thing and grew a control in only one of them.
--}}

{{--
    The one piece of styling this package cannot express in utilities, so it
    ships it rather than asking the host for a keyframe. A host that had not
    been told about it would simply not shake, which is the sort of thing
    nobody notices is missing.

    Small on purpose: a king that is being warned about, not a king having a
    fit. It settles rather than shaking forever, because a check lasts until
    somebody answers it and an animation that never stops stops being read.

    Off entirely for anybody who has asked for less movement — the ring is
    still there, and the room refuses an illegal move regardless.
--}}
<style>
    @keyframes chess-check {
        0%, 60%, 100% { transform: translateX(0) rotate(0); }
        10% { transform: translateX(-7%) rotate(-5deg); }
        20% { transform: translateX(7%) rotate(5deg); }
        30% { transform: translateX(-5%) rotate(-4deg); }
        40% { transform: translateX(5%) rotate(4deg); }
        50% { transform: translateX(-2%) rotate(-2deg); }
    }

    .chess-piece[data-check] {
        animation: chess-check 1.6s ease-in-out infinite;
        transform-origin: 50% 85%;
    }

    /*
        Which side a piece is on, said in an attribute rather than in a class.
        Safari would not take the class off again.

        A square only ever goes from holding a white piece to holding a black
        one when black captures. Safari left the pearl class on and added the
        dark one under it, so a pawn came out ivory on the square of the bishop
        it had just taken. The other direction looked fine, because adding is
        the half that worked. Alpine does this correctly against a spec DOM,
        which is why it survived being read several times.

        An attribute holds one value. Nothing is added or removed, so there is
        nothing to be left behind, in this engine or the next one.
    */
    /* The player rows draw the same pieces and use these too. */
    .chess-piece { fill: #1e293b; }

    /*
        A piece you have picked up sits above the board.

        Dragging one already lifts it — it is carried at a size of its own,
        under the cursor — and selecting one by clicking is the same act with a
        different gesture, so it reads the same. Without this the two ways of
        picking a piece up looked like two different things happening.

        Only on the board. The carried piece is positioned by a transform of its
        own and would lag behind the cursor if it had a transition on it, and
        the pieces in the player rows are not picked up at all.
    */
    .chess-on-board { transition: transform 120ms ease-out; }
    .chess-on-board[data-lifted] { transform: scale(1.18); }

    @media (prefers-reduced-motion: reduce) {
        .chess-on-board { transition: none; }
    }
    .chess-piece[data-side='white'] { fill: #dcd6cc; }
    .dark .chess-piece[data-side='white'] { fill: #c0b6a2; }

    @media (prefers-reduced-motion: reduce) {
        .chess-piece[data-check] { animation: none; }
    }
</style>
{{--
    Eight files, and the same grid whichever way up you are sitting.

    Takes the width it is given up to a ceiling, rather than being
    eight squares of a fixed size. A board measured in pixels per
    square runs off the side of a phone and is lost in the middle of
    a desktop; measured as a share of the space, it is the same board
    on both.

    A thicker edge along the bottom than around the sides, in the
    darker cut of the squares' own colour. It is the whole of the
    dimensionality: enough to read as a solid object sitting on the
    page rather than a pattern printed on it.

    On a phone it runs to both edges, which is what buys the squares
    enough size to aim at with a thumb.

    On a phone it cancels exactly one padding: Flux's main area
    applies `p-6`, and this takes 1.5rem back off each side.

    It took three tries to be worth this little. Cancelling the
    page's own padding left the layout's; measuring against the
    viewport put a 100vw element inside Flux's body grid, where the
    main column is a track rather than the screen. Both were guesses
    at a number nobody had looked up. `flux:main` says `p-6 lg:p-8`
    in its own source, and once the screen stopped padding twice
    there was one padding left with a known value.

    Below `sm` only — at `lg` the main area pads by `p-8` and the
    board is nowhere near the edges anyway.

    On a phone the sides lose their border and their rounding: the
    board actually reaches the edges now, and an edge drawn against
    the edge of the screen is a line with nothing on the far side of
    it. Top and bottom keep theirs, and the thick bottom is what
    still makes it an object.

    This was tried once before it was true — when the board stopped
    short of the edges, dropping the sides only made it look
    unfinished.
--}}
{{--
    A board nobody is connected to goes grey and stops taking touches.

    Otherwise a dropped table looks exactly like a live one — the pieces are
    where they were, the names are still under them — and the only sign is a
    move that quietly does nothing. Grey says it before anybody tries.

    `pointer-events-none` as well as the colour, because `myMove` already
    refuses the click but refusing it silently is the thing being fixed.
--}}
<div
    class="max-sm:-mx-6 max-sm:w-[calc(100%+3rem)] w-full transition sm:max-w-[26rem] lg:max-w-[34rem] xl:max-w-[38rem]"
    :class="disconnected ? 'pointer-events-none opacity-60 grayscale' : ''"
>
    <div class="grid grid-cols-8 overflow-hidden border-x-0 border-t-2 border-b-[10px] border-slate-300 sm:rounded-lg sm:border-x-2 dark:border-slate-950">
        <template x-for="cell in squares" :key="cell.name">
            {{--
                Two ways to make the same move.

                Clicking is unchanged. Dragging is added on top and goes through
                the same `choose`, so the sound, the selection, the legal
                squares and the refusal are all in one place — two
                implementations of "play this move" would be two things to keep
                in step.

                `data-square` because a drop is worked out from what is under
                the cursor: the pointer is captured by the square the drag
                started on, so every event claims to be over that one.

                `touch-none` only on a piece you could actually pick up. On a
                phone the board is most of the screen, and taking touch away
                from all of it would mean a page that cannot be scrolled.
            --}}
            <button
                type="button"
                :data-square="cell.name"
                @click="clicked(cell.name)"
                @pointerdown="startDrag($event, cell.name)"
                @pointermove="moveDrag($event)"
                @pointerup="endDrag($event)"
                @pointercancel="drag = null; holding = null"
                :disabled="!myMove"
                :title="cell.piece ? `${cell.white ? 'white' : 'black'} ${cell.piece.name} on ${cell.name}` : cell.name"
                :class="[
                    cell.dark ? 'bg-slate-200 dark:bg-slate-700' : 'bg-white dark:bg-slate-500',
                    myMove ? 'cursor-pointer' : 'cursor-default',
                    canMoveFrom(cell.name) ? 'touch-none' : '',
                ]"
                class="relative flex aspect-square w-full items-center justify-center"
            >
                {{--
                    Font Awesome Free, CC BY 4.0. Solid-only, so the
                    two sides share a silhouette and are told apart
                    by fill, the way a real set is.

                    Both sides are stickers: a white border cut
                    round the shape, the same on each, and only the
                    fill telling them apart. The white pieces are
                    off-white rather than white so there is a fill
                    to see inside their own border.

                    A warm pearl against a blue-black, which is the
                    pairing a real set has: ivory is never white and
                    the dark side is never quite black. Cooling the
                    pale side to match the dark one made it read as
                    grey rather than as a material.

                    One border colour and one weight for both, which
                    is why they read as one set — four colours and
                    two stroke widths was where this started, and it
                    looked like two drawings sharing a board.

                    `paint-order:stroke` puts the outline underneath the
                    fill, so it reads as an edge rather than as a piece
                    that has been thinned. Without it a white piece on a
                    light square is very nearly invisible, which is what
                    the Unicode glyphs used to give us.

                    A shadow lifts them off the squares. Offset
                    downward and barely blurred, so it falls from
                    the foot of the piece the way a shadow does for
                    something standing on a board — a shadow spread
                    evenly around a shape reads as the shape
                    floating instead.

                    A filter on the whole element rather than
                    anything in the artwork, so it follows the
                    silhouette, outline included, and costs the path
                    data nothing.

                    Eight-digit hex rather than an rgb() with an
                    alpha slash, because a bracketed utility that
                    fails to parse produces no shadow at all rather
                    than an error — and nothing here can tell the
                    difference between that and a subtle one.
                --}}
                {{--
                    The piece you have picked up, marked the way its
                    destinations are.

                    A hollow circle where an empty destination has a filled one,
                    at the same size and in the same green. It replaces a ring
                    drawn around the whole square, which was the only green on
                    the board that was not a circle.

                    Under the piece, which is where it was asked to be. The
                    piece is about three times its diameter and centred over it,
                    so most of the time there will be nothing to see — the
                    alternative on offer was a ring around the piece, at the
                    size the capture indicator uses.
                --}}
                <span
                    x-show="selected === cell.name"
                    x-cloak
                    class="absolute size-3 rounded-full border-2 border-emerald-400/70"
                ></span>

                <svg
                    x-show="cell.piece && drag?.from !== cell.name"
                    viewBox="0 0 512 512"
                    class="chess-piece chess-on-board relative size-[65%] overflow-visible stroke-white stroke-[66] drop-shadow-[0_3px_2px_#00000059] [paint-order:stroke]"
                    :data-side="cell.white ? 'white' : 'black'"
                    :data-check="inCheck(cell) ? 'yes' : null"
                    :data-lifted="selected === cell.name ? 'yes' : null"
                    aria-hidden="true"
                >
                    <path :d="cell.piece?.path" :transform="cell.piece?.transform"></path>
                </svg>

                {{--
                    Where the piece you are holding may go.

                    Empty squares only. A square with somebody's piece on it
                    used to be ringed and is not marked at all now — one shape,
                    on the squares it fits on, rather than a second treatment
                    for the same idea.

                    Worth knowing what that costs: which captures are available
                    no longer shows on the board. The room still refuses
                    anything illegal, so a capture you cannot see is one you can
                    still try.
                --}}
                <span
                    x-show="isTarget(cell.name) && !cell.piece"
                    class="absolute size-3 rounded-full bg-emerald-400/70"
                ></span>
            </button>
        </template>
    </div>

    {{--
        The piece in your hand.

        Outside the grid and fixed to the viewport, so it is not clipped by the
        board's own `overflow-hidden` and can be carried past the edge without
        disappearing. Centred on the cursor and slightly larger than the piece
        it came from — it is being held above the board rather than sitting on
        it, and the shadow is the same one the squares use, thrown further.

        `pointer-events-none` so it is never what `elementFromPoint` finds when
        the drag ends. It follows the cursor exactly, so it would be under it
        every time.
    --}}
    <template x-if="drag">
        <svg
            viewBox="0 0 512 512"
            class="chess-piece pointer-events-none fixed z-50 size-16 -translate-x-1/2 -translate-y-1/2 overflow-visible stroke-white stroke-[66] drop-shadow-[0_8px_6px_#00000059] [paint-order:stroke]"
            :data-side="drag.cell?.white ? 'white' : 'black'"
            :style="`left: ${drag.x}px; top: ${drag.y}px`"
            aria-hidden="true"
        >
            <path :d="drag.cell?.piece?.path" :transform="drag.cell?.piece?.transform"></path>
        </svg>
    </template>
</div>
