<?php

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Livewire\Attributes\Title;
use Livewire\Component;
use StreetMesh\Chess\ChessExperience;
use StreetMesh\Venue\Gatherings\Gathering;
use StreetMesh\Venue\Gatherings\Gatherings;
use StreetMesh\Venue\Gatherings\Seat;
use StreetMesh\Venue\Visitors;

new #[Title('Chess')] class extends Component
{
    public string $key = '';

    public function mount(string $key): void
    {
        $this->key = $key;
    }

    public function game(): ?Gathering
    {
        return Gathering::query()
            ->where('experience', ChessExperience::COLLECTION)
            ->keyed($this->key)
            ->first();
    }

    /**
     * Which chair this visitor is in, or empty if they are watching.
     */
    public function seat(): string
    {
        return (string) ($this->place()?->seat ?? '');
    }

    /**
     * Whether they have a place here at all.
     *
     * Not the same question as which chair. Somebody watching has a place and
     * an empty seat; somebody who has just followed an invitation has neither,
     * and asking the venue for a ticket on their behalf is how this screen used
     * to greet them with "That visitor has no place there".
     */
    /**
     * Who is playing each side, by the name their own server gave them.
     *
     * From the seats rather than the room, because this is who the game belongs
     * to — it stays true while somebody is reconnecting, and it is what a
     * stranger looking at an invitation sees before any socket is opened.
     *
     * @return array<string, string>
     */
    public function players(): array
    {
        $game = $this->game();

        if ($game === null) {
            return [];
        }

        $players = [];

        foreach ($game->seats()->with('delegation')->whereIn('seat', ['white', 'black'])->get() as $seat) {
            $players[$seat->seat] = (string) ($seat->delegation?->handle ?? '');
        }

        return $players;
    }

    public function seated(): bool
    {
        return $this->place() !== null;
    }

    /**
     * The way to this table, as something a phone camera can read.
     *
     * Two people at one table are often two people in one room, and the way
     * they have been handing a game over is by reading a URL out loud or
     * typing it into a message. A code on the screen is the shortest path
     * between "look at this" and a board on somebody else's phone.
     *
     * Drawn here rather than in the browser. The address is already known on
     * this side, the answer never changes for a given game, and a QR encoder in
     * JavaScript would be a second one — the server already has this one, for
     * two-factor codes.
     *
     * The whole address goes in, absolute. A code holding a path would scan to
     * nothing on the phone that read it, which is the only device that ever
     * scans it.
     *
     * SVG so it stays sharp on whatever it is shown on, and so it needs no
     * image extension installed to render.
     */
    public function qr(): string
    {
        $invitation = route('chess.table', $this->key);

        return (new Writer(new ImageRenderer(
            new RendererStyle(320, margin: 1),
            new SvgImageBackEnd,
        )))->writeString($invitation);
    }

    /**
     * Whether whoever is looking at this came through the door.
     *
     * Not the same as having a place here — most people reading a board have
     * neither, and one of the two is what decides whether a link into the rest
     * of the venue leads anywhere they can go.
     */
    public function arrived(): bool
    {
        return app(Visitors::class)->current(request()) !== null;
    }

    private function place(): ?Seat
    {
        $visitor = app(Visitors::class)->current(request());
        $game = $this->game();

        if ($visitor === null || $game === null) {
            return null;
        }

        // Asked by who they are. Keyed on the delegation, somebody who came
        // back through the door was shown as watching their own game.
        return app(Gatherings::class)->seatOf($game, $visitor);
    }
};?>

{{--
    No padding of its own. Flux's main area already applies `p-6 lg:p-8`, and
    padding again is how this screen ended up with twice the margins of the
    rest.
--}}
{{--
    Takes the height it is given, so the board can sit in the middle of it —
    above `sm`, and not on a phone.

    Flux's main area is a row of the body's grid and already has a height; this
    fills it rather than collapsing to the height of a chessboard, and the group
    below centres inside what the header leaves.

    On a phone it does neither. The body is `min-h-screen`, which is `100vh`,
    and on iOS that is the *large* viewport — the height with the toolbar
    hidden. Centring inside a box taller than the screen pushes the bottom of
    the board underneath the address bar, where it cannot be scrolled to.

    There is nothing to centre there anyway: the board takes the full width, so
    the screen is already full. Let it flow from the top and scroll like a page.
--}}
<div class="flex flex-col gap-6 sm:min-h-full">
    @php($game = $this->game())

    @if ($game === null)
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:callout.heading>{{ __('There is no game here') }}</flux:callout.heading>
            <flux:callout.text>{{ __('It may have finished, or the link may be wrong.') }}</flux:callout.text>
        </flux:callout>
    @endif

    @if ($game !== null && ! $game->isOpen())
        {{--
            A game that is over, drawn from what the venue kept rather than
            from a room — the hub forgot it when the last person left, so
            joining one is how somebody coming back to look was once shown
            "That is over" as though they had done something wrong.

            The same component the live board becomes when a game ends, given
            the record instead of a socket. One set of markup for one thing.
        --}}
        @php($outcome = $game->outcome ?? [])

        <div
            wire:ignore
            x-data="chessReplay({
                positions: @js(array_values((array) ($outcome['positions'] ?? []))),
                moves: @js(array_values((array) ($outcome['moves'] ?? []))),
                seat: @js($this->seat()),
                outcome: @js((string) ($outcome['outcome'] ?? '')),
                winner: @js((string) ($outcome['winner'] ?? '')),
                white: @js($this->players()['white'] ?? ''),
                black: @js($this->players()['black'] ?? ''),
            })"
            class="flex w-full flex-1 flex-col gap-4"
        >
            @include('chess::header')

            @if (($outcome['positions'] ?? []) !== [])
                {{-- Centred in what the header leaves, above `sm`. See the top of this file. --}}
                <div class="flex w-full flex-col items-center gap-4 sm:m-auto">
                    @include('chess::player', ['side' => 'far'])

                    @include('chess::board')

                    @include('chess::player', ['side' => 'near'])
                </div>
            @endif
        </div>
    @elseif ($game !== null)
        {{--
            The board is drawn and driven by the hub, not by Livewire.

            A move has to be refused by the referee rather than by this page, so
            the page holds no opinion about the rules at all — it renders what
            the room says and asks for what somebody clicked. Everything below
            is scaffolding around a websocket.

            `wire:ignore` because Livewire must not re-render underneath it: a
            round trip to PHP between two clicks would fight the live state.

            Everybody looking at this page asks for a ticket, not only the two
            people playing. Watching a game means watching it happen, and a
            board that opened no socket for a passer-by showed them the position
            as it stood when the page loaded and then nothing ever again.

            Whether they get one is the venue's answer, and behind that the
            experience's. A request that should be refused comes back refused,
            and the board goes on showing the position it already has.
        --}}
        <div
            wire:ignore
            x-data="chessTable({
                ticketUrl: @js(route('venue.ticket', $game->key)),
                settleUrl: @js(route('chess.settle', $game->key)),
                seat: @js($this->seat()),
                invitation: @js(route('chess.table', $game->key)),
                white: @js($this->players()['white'] ?? ''),
                black: @js($this->players()['black'] ?? ''),
            })"
            class="flex w-full flex-1 flex-col gap-4"
        >
            @include('chess::header')

            {{--
                Said over the board rather than above it.

                It is up for two and a half seconds. In the flow it moved the
                board down on arrival and back up on the way out, which reads as
                the board flinching at a message about something else.
            --}}
            <div class="relative z-10 h-0">
                <template x-if="trouble">
                    <flux:callout variant="danger" icon="exclamation-triangle" class="absolute inset-x-0 top-0">
                        <flux:callout.text x-text="trouble"></flux:callout.text>
                    </flux:callout>
                </template>
            </div>

            {{--
                Who is playing, on the side of the board they are sitting at.

                The far one above and the near one below, which is where they
                would be if this were a table — and for somebody watching, who
                is on neither side, black is above the way a board is drawn when
                nobody in particular is looking at it.
            --}}
            {{-- Centred in what the header leaves, above `sm`. See the top of this file. --}}
            <div class="flex w-full flex-col items-center gap-4 sm:m-auto">
                @include('chess::player', ['side' => 'far'])

                @include('chess::board')

                @include('chess::player', ['side' => 'near'])
            </div>
        </div>

        {{--
            This table is somewhere people can talk.

            The venue's conversation, not ours — this experience decides that a
            table is a place people may talk in, and nothing more. What a
            conversation is, who may read one and where it is kept are the
            venue's business, the same way seating and audiences already are.

            The space is the room's own name, so a table and the room it is
            played in are one place rather than two that have to be kept in
            step.
        --}}
        @if ($this->game())
            <div
                hidden
                data-streetmesh-space="{{ $this->game()->room() }}"
                data-streetmesh-label="{{ __('Table') }}"
            ></div>
        @endif
    @endif
</div>
