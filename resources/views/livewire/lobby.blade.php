<?php

use Livewire\Attributes\Title;
use Livewire\Component;
use StreetMesh\Chess\ChessExperience;
use StreetMesh\Chess\Games;
use StreetMesh\Venue\Gatherings\Gathering;
use StreetMesh\Venue\Gatherings\Gatherings;
use StreetMesh\Venue\Gatherings\Results;
use StreetMesh\Venue\Visitors;

new #[Title('Chess')] class extends Component
{
    /**
     * Games anybody could join, most recent first.
     *
     * @return \Illuminate\Support\Collection<int, Gathering>
     */
    public function open(): \Illuminate\Support\Collection
    {
        return Gathering::query()
            ->where('experience', ChessExperience::COLLECTION)
            ->where('status', Gathering::OPEN)
            ->with(['seats' => fn ($seats) => $seats->whereIn('seat', ['white', 'black'])->with('delegation')])
            ->latest()
            ->get();
    }

    /**
     * What has happened here, most recent first.
     *
     * From what the venue kept when each game concluded. The hub forgot every
     * one of these the moment its last player left, so this is the only place
     * left that knows a game was ever played.
     *
     * @return \Illuminate\Support\Collection<int, Gathering>
     */
    public function finished(): \Illuminate\Support\Collection
    {
        return Gathering::query()
            ->where('experience', ChessExperience::COLLECTION)
            ->where('status', Gathering::CONCLUDED)
            ->latest('concluded_at')
            ->limit(8)
            ->get();
    }

    /**
     * Whether whoever is reading this came through the door.
     *
     * The screen is at two addresses: one for people who came to play, and one
     * anybody may read. Everything that acts already refuses without a visitor,
     * and this is what stops those things being offered in the first place.
     */
    public function arrived(): bool
    {
        return app(Visitors::class)->current(request()) !== null;
    }

    /**
     * Who is actually at each open table, asked of the hub.
     *
     * Kept apart from the games themselves because they answer different
     * questions. A seat is the venue's record that somebody may play and
     * outlives them closing the tab — it has to, or an opponent could take
     * their chair while they reconnected. This is the room right now.
     *
     * @return array<string, array<int, array{name: string, seat: string}>>
     */
    public function present(): array
    {
        return app(Results::class)->at($this->open());
    }

    /**
     * Who is playing, by the names their own servers gave them.
     *
     * From the seats rather than from the room: this is who the game belongs
     * to, and it stays true while somebody is reconnecting. Who is *there* is a
     * separate line, and a separate question.
     *
     * @return array<string, string> handle by seat
     */
    public function players(Gathering $game): array
    {
        $players = [];

        foreach ($game->seats as $seat) {
            $players[$seat->seat] = (string) ($seat->delegation?->handle ?? '');
        }

        return $players;
    }

    /**
     * What going in would actually get you.
     *
     * Somebody already holding a chair is going back to it, whatever anybody
     * else has done since — a game with a full audience still says "Play" to
     * the person sitting in white. That was the bug: this counted every seat,
     * so watchers filled the table and a player was offered a seat in the
     * audience at their own game.
     *
     * Only the two chairs decide whether a stranger can play. The audience is
     * unbounded and is not part of the question.
     */
    public function action(Gathering $game): string
    {
        $visitor = app(Visitors::class)->current(request());

        /*
         * Nobody who has not arrived is being offered a game.
         *
         * A free chair is not an invitation to somebody the venue cannot name,
         * and this said "Play" to every stranger reading the public list —
         * a word the venue was in no position to honour, on a button that then
         * did nothing at all when pressed.
         */
        if ($visitor === null) {
            return __('Watch');
        }

        $mine = app(Gatherings::class)->seatOf($game, $visitor);

        if ($mine !== null) {
            return $mine->seat === '' ? __('Watch') : __('Play');
        }

        return count($this->players($game)) < 2 ? __('Play') : __('Watch');
    }

    public function start(): void
    {
        $visitor = app(Visitors::class)->current(request());

        if ($visitor === null) {
            return;
        }

        $game = app(Games::class)->open($visitor);

        $this->redirectRoute('chess.table', $game->key, navigate: true);
    }

    public function sit(string $key): void
    {
        $visitor = app(Visitors::class)->current(request());
        $game = Gathering::query()->keyed($key)->first();

        if ($visitor === null || $game === null) {
            return;
        }

        app(Games::class)->join($game, $visitor);

        $this->redirectRoute('chess.table', $game->key, navigate: true);
    }
};?>

{{--
    No padding of its own. The host's layout already pads the main area — Flux
    applies `p-6 lg:p-8` to it — and a screen that pads again is a screen with
    twice the margins of every other one, which is exactly how this looked.
--}}
{{--
    Listening rather than asking.

    Who is at a table changes without anybody on this screen doing anything, so
    it used to poll — which meant being wrong for up to half a minute and busy
    for the rest of it. The venue now hears every arrival and departure from the
    hub and says so, and this waits to be told.

    The poll stays as a slow backstop. A dropped socket is silent by nature, and
    a screen that had quietly stopped listening would look exactly like a venue
    where nothing was happening.
--}}
<div
    class="flex flex-col gap-6"
    wire:poll.120s
    x-data
    x-init="
        window.Echo?.channel('streetmesh.experience.{{ ChessExperience::COLLECTION }}')
            .listen('.StreetMesh\\Venue\\Realtime\\Occupied', () => $wire.$refresh())
    "
>
    <div class="flex items-center justify-between gap-4">
        <flux:heading size="xl">{{ __('Chess') }}</flux:heading>

        {{--
            Only to somebody who could actually start one.

            This list is readable by anybody now, and `start` refuses without a
            visitor — so a stranger reading it would be shown a button that did
            nothing whatever when pressed. The way in is offered instead, which
            is what they would have needed first anyway.
        --}}
        @if ($this->arrived())
            <flux:button wire:click="start" variant="primary" icon="plus">
                {{ __('Start a game') }}
            </flux:button>
        @else
            <flux:button :href="route('venue.connect')" variant="primary" wire:navigate>
                {{ __('Sign in to play') }}
            </flux:button>
        @endif
    </div>

    @php($present = $this->present())

    @forelse ($this->open() as $game)
        @php($here = $present[$game->room()] ?? null)

        <flux:card class="flex items-center justify-between gap-4">
            <div class="flex flex-col gap-1">
                {{--
                    Three lines, answering three different questions: which
                    table this is, whose game it is, and whether anybody is
                    there.

                    The key names the table and never changes, which is what
                    makes it worth keeping — it is how you tell two games
                    between the same two people apart, and what somebody would
                    read back to you.
                --}}
                {{--
                    Both chairs named, even when nobody is in one.

                    `players()` returns only the seats that exist, and a seat
                    stops existing the moment its permission is given back —
                    revoking deletes the delegation and the seat goes with it.
                    So a game can be left with a black player and no white, and
                    every line below that read `$players['white']` was one
                    undefined key away from taking the whole lobby down. It did.
                --}}
                @php($players = $this->players($game) + ['white' => '', 'black' => ''])

                <flux:heading>{{ __('Game :key', ['key' => Str::of($game->key)->substr(-6)]) }}</flux:heading>

                @if ($players['white'] !== '' || $players['black'] !== '')
                    {{--
                        Labels rather than whole addresses, the same convention
                        the board uses. The whole handle stays in the title.
                    --}}
                    @php($label = fn (string $handle): string => Str::before($handle, '.'))

                    <flux:text
                        class="text-sm"
                        :title="trim($players['white'].' '.$players['black'])"
                    >
                        @if ($players['white'] !== '' && $players['black'] !== '')
                            {{ $label($players['white']) }} {{ __('vs') }} {{ $label($players['black']) }}
                        @else
                            {{ __(':player is waiting for an opponent', [
                                'player' => $label($players['white'] ?: $players['black']),
                            ]) }}
                        @endif
                    </flux:text>
                @endif

            </div>

            {{--
                What the button offers is what you would actually get. Both
                chairs taken means watching, and saying "Play" there would be a
                promise the venue is about to break.

                Still the venue's answer either way — this only reads the same
                thing the venue is about to decide, and being wrong about it
                costs nothing but the word.
            --}}
            <div class="flex shrink-0 items-center gap-3">
                {{--
                    How many people are actually at that table, beside the way
                    in. A mark and a number rather than a sentence: it is a
                    reading, and it changes on its own while you are looking at
                    it — the same reason it is not part of the description.

                    Everybody, playing or watching. Which of the two you would
                    be is what the button says.

                    Absent entirely when the hub did not answer, rather than
                    zero. A number this server cannot stand behind is worse
                    than no number.
                --}}
                @if ($here !== null)
                    <flux:text class="flex items-center gap-1.5 text-sm tabular-nums">
                        <flux:icon name="eye" class="size-4 shrink-0 text-slate-400" />
                        {{ count($here) }}
                    </flux:text>
                @endif

                {{--
                    A link for somebody who has not arrived, and an action for
                    somebody who has.

                    `sit` takes a chair, and it refuses without a visitor — so
                    on the public list this was a button that asked "really?"
                    of nobody and did nothing when clicked. A stranger goes
                    straight to the table instead, which is readable by anybody
                    and is the whole of what watching is.
                --}}
                @if ($this->arrived())
                    <flux:button wire:click="sit('{{ $game->key }}')" variant="outline">
                        {{ $this->action($game) }}
                    </flux:button>
                @else
                    <flux:button :href="route('chess.table', $game->key)" variant="outline" wire:navigate>
                        {{ $this->action($game) }}
                    </flux:button>
                @endif
            </div>
        </flux:card>
    @empty
        {{--
            The sentence under this explained federation to somebody who wanted
            a game of chess. "Start a game" is already the only other thing on
            the screen.
        --}}
        <flux:callout icon="squares-2x2">
            <flux:callout.heading>{{ __('No games in progress') }}</flux:callout.heading>
        </flux:callout>
    @endforelse

    {{--
        What has happened here.

        Only the venue knows: the hub forgot each of these when its last player
        left, and the record that counts is on the servers the players live on
        rather than this one.
    --}}
    @if ($this->finished()->isNotEmpty())
        <div class="mt-2 flex flex-col gap-3">
            <flux:heading size="lg">{{ __('Finished') }}</flux:heading>

            @foreach ($this->finished() as $game)
                @php($outcome = $game->outcome ?? [])

                <flux:card
                    :href="route('chess.table', $game->key)"
                    wire:navigate
                    class="flex items-center justify-between gap-4"
                >
                    <flux:text class="text-sm">
                        {{ __('Game :key', ['key' => Str::of($game->key)->substr(-6)]) }}
                    </flux:text>

                    <flux:badge size="sm" :color="($outcome['winner'] ?? '') !== '' ? 'emerald' : 'zinc'">
                        @if (($outcome['winner'] ?? '') !== '')
                            {{ __(':winner won', ['winner' => ucfirst((string) $outcome['winner'])]) }}
                        @else
                            {{ __('Drawn') }}
                        @endif
                        @if (($outcome['outcome'] ?? '') !== '')
                            — {{ $outcome['outcome'] }}
                        @endif
                    </flux:badge>
                </flux:card>
            @endforeach
        </div>
    @endif

    {{--
        This screen is somewhere people can talk.

        All this experience does is say so. The conversation itself is the
        venue's — it lives in the badge in the corner along with the party and
        the cameras, so that one thing on screen is where talking happens rather
        than one per experience.

        Named after this experience rather than called `/lobby`, because a venue
        with three experiences installed would otherwise have three lobbies
        sharing one conversation.
    --}}
    {{-- A mark on the page rather than a call, because the page is what gets
         swapped when somebody navigates: read from here it is always the
         current answer, where a value pushed in by script would outlive the
         screen that pushed it. --}}
    <div
        hidden
        data-streetmesh-space="{{ \StreetMesh\Chess\ChessExperience::COLLECTION }}/lobby"
        data-streetmesh-label="{{ __('Lobby') }}"
    ></div>
</div>
