<?php

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use StreetMesh\Protocol\Laravel\Permissions\Delegation;
use StreetMesh\Venue\Comms;
use StreetMesh\Venue\Parties\Invitation;
use StreetMesh\Venue\Parties\Parties;
use StreetMesh\Venue\Parties\Party;
use StreetMesh\Venue\Visitors;

/**
 * Everything to do with talking, on one panel.
 *
 * Two tabs, because there are two conversations and they are different in kind.
 * The room is wherever you happen to be standing and everybody there can read
 * it; the party is who you came with and travels with you between experiences.
 *
 * Which room this is arrives from the page rather than being worked out here.
 * Only an experience knows whether one of its screens is a place people can
 * talk in, and a venue guessing from a URL would put a chat box on a settings
 * page.
 */
new class extends Component
{
    public string $tab = 'room';

    /** The space this screen is, as the experience around us named it. */
    public string $space = '';

    public string $spaceLabel = '';

    public string $joining = '';

    public string $inviting = '';

    /**
     * Whether the reader has picked a tab themselves.
     *
     * Until they do, the panel follows the page: a screen that is a room opens
     * on the room, and one that is not opens on the party, because an empty tab
     * is a worse first thing to see than the wrong one. Once somebody has
     * chosen, walking into a room stops moving the panel out from under them.
     */
    public bool $chosen = false;

    /**
     * Whether the party strip should be open when it first appears.
     *
     * Somebody who has just started one has a party of themselves and needs the
     * word that lets anybody else in — which lives in the drawer. Leaving it
     * shut means the first thing they do is hunt for the thing they obviously
     * came for.
     *
     * Read once, when the strip is drawn for the first time. Shutting it
     * afterwards is the browser's business and nothing here overrides that.
     */
    public bool $detailsOpen = false;

    public function mount(): void
    {
        /*
         * No context has arrived yet — it comes from the host document a moment
         * after this renders — so this is the answer for "nowhere in
         * particular", which is what nowhere-yet looks like.
         */
        $this->tab = $this->parties()->enabled() ? 'party' : 'room';
    }

    #[On('comms-context')]
    public function context(string $space = '', string $label = ''): void
    {
        $this->space = $space;
        $this->spaceLabel = $label;

        if (! $this->chosen) {
            $this->tab = $space !== '' ? 'room' : ($this->parties()->enabled() ? 'party' : 'room');

            /* The browser holds which tab is showing, so it has to be told when
               the page changes its mind about the default. */
            $this->dispatch('comms-tab', tab: $this->tab);
        }

        unset($this->roster, $this->invitations, $this->here);
    }

    private function parties(): Parties
    {
        return app(Parties::class);
    }

    private function visitor(): ?Delegation
    {
        return app(Visitors::class)->current(request());
    }

    #[Computed]
    public function offered(): bool
    {
        return $this->parties()->enabled();
    }

    #[Computed]
    public function party(): ?Party
    {
        return $this->parties()->partyOf($this->visitor());
    }

    /** @return Collection<int, Delegation> */
    #[Computed]
    public function roster(): Collection
    {
        $party = $this->party();

        return $party === null ? new Collection : $this->parties()->rosterOf($party);
    }

    /** @return Collection<int, Invitation> */
    #[Computed]
    public function invitations(): Collection
    {
        return $this->parties()->invitationsFor($this->visitor());
    }

    /** @return Collection<int, Delegation> */
    #[Computed]
    public function here(): Collection
    {
        $visitor = $this->visitor();

        return $visitor === null ? new Collection : $this->parties()->here($visitor);
    }

    #[Computed]
    public function full(): bool
    {
        return $this->roster()->count() >= $this->parties()->size();
    }

    public function start(): void
    {
        $this->run(fn (Delegation $me) => $this->parties()->open($me));

        /* A party of one, and the code is what changes that. */
        $this->detailsOpen = true;
    }

    public function join(): void
    {
        if (trim($this->joining) === '') {
            return;
        }

        $this->run(fn (Delegation $me) => $this->parties()->joinByCode($this->joining, $me));

        $this->joining = '';
    }

    public function invite(): void
    {
        $party = $this->party();

        if ($party === null || $this->inviting === '') {
            return;
        }

        $this->run(fn (Delegation $me) => $this->parties()->invite($party, $me, $this->inviting));

        $this->inviting = '';
    }

    public function accept(int $invitation): void
    {
        $offer = Invitation::find($invitation);

        if ($offer !== null) {
            $this->run(fn (Delegation $me) => $this->parties()->accept($offer, $me));
        }
    }

    public function decline(int $invitation): void
    {
        $offer = Invitation::find($invitation);

        if ($offer !== null) {
            $this->run(fn (Delegation $me) => $this->parties()->decline($offer, $me));
        }
    }

    public function rotate(): void
    {
        $party = $this->party();

        if ($party !== null) {
            $this->run(fn (Delegation $me) => $this->parties()->rotateCode($party, $me));
        }
    }

    public function leave(): void
    {
        $party = $this->party();

        if ($party !== null) {
            $this->run(fn (Delegation $me) => $this->parties()->leave($party, $me));
        }
    }

    /**
     * Do something that may refuse, and say why on the panel if it does.
     *
     * Everything `Parties` throws is a sentence worth reading — the party
     * filled up, the code answers to nobody, you are already in one — so they
     * all arrive the same way.
     *
     * The stage is told afterwards because joining or leaving a party changes
     * which room its media belongs to, and it is a separate document that
     * cannot see this happen.
     */
    private function run(callable $work): void
    {
        $visitor = $this->visitor();

        if ($visitor === null) {
            return;
        }

        try {
            $work($visitor);
        } catch (Throwable $refused) {
            $this->addError('party', $refused->getMessage());
        }

        unset($this->party, $this->roster, $this->invitations, $this->here, $this->full);

        /*
         * Tell the page, which is where the media lives now.
         *
         * Starting, joining or leaving a party changes which room the camera
         * belongs to, and none of it reloads the page — so without this the
         * host would go on holding connections to a party somebody has left,
         * or none to the one they just joined.
         */
        $this->dispatch('party-changed', party: app(Comms::class)
            ->forHost(request())['party']);
    }
}; ?>

<div
    class="flex h-full flex-col bg-white dark:bg-zinc-900"
    wire:poll.5s
    x-data="{
        /*
         * Kept where it was chosen rather than in memory.
         *
         * This element polls, and every poll morphs it — which writes the
         * server's idea of the tab back into this very attribute. Anything held
         * only in memory here is therefore held until the next re-render and no
         * longer, which is a tab that switches and then switches back a moment
         * later for no reason the reader can see.
         *
         * Reading it back out of the browser on every initialisation makes that
         * harmless: however often this is re-made, it is re-made on the tab
         * somebody actually picked. It also survives the panel reloading, which
         * it does whenever the page around it navigates.
         */
        tab: sessionStorage.getItem('smCommsTab') || @js($tab),

        /**
         * Whether the reader has picked for themselves.
         *
         * The server keeps its own answer to this and is the reason the panel
         * follows the page until somebody chooses. But it learns of a choice a
         * request later, and in that gap it will go on sending defaults — so
         * the browser keeps the answer too, and it is this one that decides.
         */
        chosen: sessionStorage.getItem('smCommsTab') !== null,

        /*
         * Who the page could not reach, read back the same way the tab is.
         *
         * This is the page's knowledge, not the server's — the server has no
         * idea which pairs of browsers found each other — and it arrives by
         * message. Every poll re-makes this element, so holding it only in
         * memory would clear the line every five seconds and bring it back
         * when the next connection failed.
         */
        unreachable: window.smUnreachable ?? [],

        /** The party itself failing, which is a different and louder thing. */
        partyTrouble: window.smPartyTrouble ?? '',

        cannotReach () {
            const who = this.unreachable

            if (who.length === 1) {
                return @js(__('Cannot connect to :name.')).replace(':name', who[0])
            }

            return @js(__('Cannot connect to :names.')).replace(':names', who.join(', '))
        },

        /**
         * Show a tab, and say so.
         *
         * A pane that was hidden has no height, so anything the conversation
         * inside it did about scrolling was done to a box of nothing. It
         * listens for this and goes back to the newest line.
         */
        show (which) {
            this.chosen = true
            sessionStorage.setItem('smCommsTab', which)

            this.reveal(which)
        },

        /**
         * Tell the server, without asking it for anything.
         *
         * The third argument is what matters: set the property and do not
         * re-render. A render here replaces the class attribute on the tab
         * that was just tapped with the one the server drew, which has none of
         * the classes saying it is the chosen one — and Alpine does not put
         * them back, because from where it stands nothing changed. So the pane
         * switched at once and the tab above it stayed grey until the next
         * poll re-made the element, five seconds later.
         *
         * Nothing waits for this and nothing needs to. The browser already
         * holds which tab is showing and whether somebody chose it; the server
         * only wants to know so it stops suggesting, and it finds out with the
         * next poll either way.
         */
        remember (which) {
            this.$wire.$set('tab', which, false)
            this.$wire.$set('chosen', true, false)
        },

        /**
         * The page's idea of where to start, which a choice outranks.
         *
         * Walking into a room moves the panel to that room's conversation, and
         * must stop doing so the moment somebody has said where they would
         * rather be.
         */
        suggest (which) {
            if (this.chosen) {
                return
            }

            this.reveal(which)
        },

        reveal (which) {
            this.tab = which

            this.$nextTick(() => window.dispatchEvent(new CustomEvent('comms-shown')))
        },
    }"
    x-on:comms-tab.window="suggest($event.detail.tab)"
    x-on:comms-unreachable.window="unreachable = $event.detail.names"
    x-on:comms-party-trouble.window="partyTrouble = $event.detail.why"
>
    {{--
        The two conversations, and which one is being read.

        Held in the browser rather than on the server. A tab is UI state, and
        making it a round trip queued every click behind whatever poll was in
        flight — which on Safari read as a long pause or as a click that did
        nothing at all. The server is told afterwards, so that the page stops
        choosing a default once somebody has chosen for themselves, and nothing
        waits for that to land.
    --}}
    <div class="flex shrink-0 border-b border-zinc-200 dark:border-zinc-700">
        <button
            type="button"
            x-on:click="show('room'); remember('room')"
            class="flex-1 px-4 py-3 text-sm"
            x-bind:class="tab === 'room'
                ? 'border-b-2 border-[var(--sm-accent)] font-semibold text-zinc-900 dark:text-[var(--sm-accent)]'
                : 'font-medium text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200'"
        >{{ $spaceLabel !== '' ? $spaceLabel : __('Room') }}</button>

        @if ($this->offered)
            <button
                type="button"
                x-on:click="show('party'); remember('party')"
                class="flex-1 px-4 py-3 text-sm"
                x-bind:class="tab === 'party'
                    ? 'border-b-2 border-[var(--sm-accent)] font-semibold text-zinc-900 dark:text-[var(--sm-accent)]'
                    : 'font-medium text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200'"
            >
                {{ __('Party') }}
                @if ($this->invitations->isNotEmpty())
                    <span class="ml-1 inline-block size-2 rounded-full bg-red-500 align-middle"></span>
                @endif
            </button>
        @endif

        {{--
            A circle rather than a bare glyph. The tabs are the thing being
            chosen between; this is one control sitting next to them, and a
            cross floating at whatever size the document happened to be reads
            as debris rather than a button.

            Sized to the row, which is also the smallest target a thumb can be
            asked to find: it was 28px, and on a phone that is a button you
            miss and then miss again. The tabs either side are 44px tall for
            the same reason, so matching them costs no height — the circle
            fills the strip it already sat in the middle of.

            The glyph grows with it. A cross at the old size inside a circle
            this size reads as a mistake rather than as a bigger button.

            Its own centred cell, so the circle lines up with the middle of the
            strip rather than the top of the text.
        --}}
        <div class="flex shrink-0 items-center pe-2">
            <button
                type="button"
                class="flex size-11 items-center justify-center rounded-full text-xl leading-none text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white"
                x-on:click="window.parent.postMessage({ method: 'streetmesh.panel.close', params: {} }, window.location.origin)"
                aria-label="{{ __('Close') }}"
            >&times;</button>
        </div>
    </div>

    {{--
        Both panes are drawn and one is hidden, which is what lets the switch be
        instant. It costs a second chat component polling in the background; a
        tab that responds when it is pressed is worth more than the query.
    --}}
    <div class="flex min-h-0 flex-1 flex-col" x-show="tab === 'room'">
        @if ($space === '')
            {{-- Nowhere in particular. A venue has screens that are not places —
                 a menu, a settings page — and pretending otherwise would put an
                 unanswerable chat box on them. --}}
            <div class="p-3">
                <flux:text>{{ __('You are not anywhere people are talking. Go into an experience and this fills up.') }}</flux:text>
            </div>
        @else
            @livewire('venue::chat', [
                'space' => $space,
                'placeholder' => __('Say something to the room'),
            ], key('room-'.md5($space)))
        @endif
    </div>

    @if ($this->offered)
        <div class="flex min-h-0 flex-1 flex-col" x-show="tab === 'party'" x-cloak>
            {{--
                Somebody in the party this browser could not reach.

                A line rather than anything that interrupts, because nothing
                here is actionable: two networks that cannot see each other are
                a circumstance, and the party carries on around it — the text
                still arrives, and everybody else is still connected.

                What it is for is telling this apart from the several bugs it
                impersonates. An empty circle that means "cannot reach them"
                and an empty circle that means "not sharing yet" looked
                identical, and the difference is a day of debugging.
            --}}
            <p
                x-show="partyTrouble"
                x-text="partyTrouble"
                x-cloak
                class="shrink-0 px-3 pt-3 text-xs text-red-600 dark:text-red-400"
            ></p>

            <p
                x-show="unreachable.length"
                x-text="cannotReach()"
                x-cloak
                class="shrink-0 px-3 pt-3 text-xs text-amber-600 dark:text-amber-500"
            ></p>

            @include('venue::comms.party')
        </div>
    @endif

    {{--
        Being heard and seen.

        Shown with the party tab rather than with a party. These began inside
        the party branch and were useless there: your own circle only appears
        once something is turned on, so before a party existed there was no
        switch anywhere on screen and no way in at all. The tab is present
        whenever parties are offered, so keying on it keeps them reachable
        while still not putting a microphone under a text conversation.

        Icons alone. The state is the variant — lit when live — and the word
        underneath was saying a second time what the colour already said, in a
        strip only wide enough for two buttons.
    --}}
    @if ($this->offered)
    <div
        class="flex shrink-0 gap-2 border-t border-zinc-200 p-3 dark:border-zinc-700"
        x-show="tab === 'party'"
        x-cloak
        x-data="{ speaking: false, showing: false }"
        x-on:message.window="
            if ($event.data?.method === 'streetmesh.stage.media') {
                speaking = $event.data.params.speaking
                showing = $event.data.params.showing
            }
        "
    >
        {{--
            No label, so the name has to be said to anything that is not a pair
            of eyes — and said in the present tense, because the button reports
            a state as much as it offers an action.
        --}}
        <flux:button
            icon="microphone"
            class="flex-1 [&_svg]:size-6"
            x-on:click="window.parent.postMessage({ method: 'streetmesh.panel.speak', params: {} }, window.location.origin)"
            ::variant="speaking ? 'primary' : 'filled'"
            aria-label="{{ __('Speak') }}"
            ::aria-label="speaking ? '{{ __('Speaking') }}' : '{{ __('Speak') }}'"
            ::title="speaking ? '{{ __('Speaking') }}' : '{{ __('Speak') }}'"
            ::aria-pressed="speaking"
        />

        <flux:button
            icon="video-camera"
            class="flex-1 [&_svg]:size-6"
            x-on:click="window.parent.postMessage({ method: 'streetmesh.panel.show', params: {} }, window.location.origin)"
            ::variant="showing ? 'primary' : 'filled'"
            aria-label="{{ __('Show') }}"
            ::aria-label="showing ? '{{ __('Showing') }}' : '{{ __('Show') }}'"
            ::title="showing ? '{{ __('Showing') }}' : '{{ __('Show') }}'"
            ::aria-pressed="showing"
        />
    </div>
    @endif
</div>
