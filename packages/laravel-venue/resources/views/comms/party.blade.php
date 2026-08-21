{{--
    The party tab.

    Laid out like a conversation rather than a form: the chat fills the pane and
    keeps its composer at the foot, exactly as the room's does, and everything
    about the party itself lives in one strip beneath it.

    That strip is mostly shut. Who is in a party and what the code is are things
    you need occasionally and read once; the conversation is what you are here
    for, so it gets the room.
--}}
@if (! $this->offered)
    <div class="p-3">
        <flux:text>{{ __('This venue does not do parties.') }}</flux:text>
    </div>
@elseif ($this->party === null)
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-3">
        @error('party')
            <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
        @enderror

        {{-- Somebody is knocking. Answered before anything else, because it is
             the part about to change what the rest of this says. --}}
        @foreach ($this->invitations as $invitation)
            <div class="flex flex-col gap-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" wire:key="asked-{{ $invitation->id }}">
                <flux:text>{{ __(':who asked you into their party.', ['who' => $invitation->invited_by_name]) }}</flux:text>

                <div class="flex gap-2">
                    <flux:button size="sm" variant="primary" wire:click="accept({{ $invitation->id }})">{{ __('Join them') }}</flux:button>
                    <flux:button size="sm" variant="subtle" wire:click="decline({{ $invitation->id }})">{{ __('No thanks') }}</flux:button>
                </div>
            </div>
        @endforeach

        <flux:button icon="user-group" wire:click="start">{{ __('Start a party') }}</flux:button>

        <flux:separator text="{{ __('or') }}" />

        <form wire:submit="join" class="flex gap-2">
            <flux:input
                wire:model="joining"
                :placeholder="__('Code')"
                autocomplete="off"
                maxlength="8"
                class="flex-1 uppercase"
            />
            <flux:button type="submit">{{ __('Join') }}</flux:button>
        </form>
    </div>
@else
    {{--
        The conversation, filling everything the drawer does not take.

        Boxed, and the box is what makes the drawer possible. The chat component
        asks for the whole height of whatever holds it — which is right on the
        room tab, where it is the only thing there, and wrong here, where it
        would take every pixel and leave the drawer to hang off the bottom of
        the panel. It did: the drawer was drawn under the row of buttons and
        painted over by it, which reads as a drawer that opens behind them.

        So the conversation gets a box that yields, and the drawer gets one that
        does not.

        It steps back while the drawer is open, and no further than that. It was
        also made deaf to the mouse, which is the wrong thing to do to something
        still sitting in plain view: the drawer pushes the conversation up
        rather than covering it, so most of it is still on screen — and a
        message box you can see, and tap, and get nothing from reads as broken
        rather than as backgrounded. Dimming says which one is being talked to;
        switching it off says something nobody meant.

        Text layers where voice supersedes: somebody cut off from the room's
        chat would miss whatever everybody around them is reacting to.
    --}}
    <div
        class="flex min-h-0 flex-1 flex-col transition-opacity"
        x-bind:class="drawer ? 'opacity-50' : ''"
    >
        @livewire('venue::chat', [
            'space' => $this->party->room(),
            'placeholder' => __('Say something to your party'),
        ], key('party-'.$this->party->key))
    </div>

    {{--
        The drawer, which opens upward over the conversation. Shut by default:
        this is reference rather than reading.

        Opened from the row of comms buttons rather than from a caret of its
        own, so the party's switch sits beside the microphone and the camera —
        the three things this panel does — instead of in a strip of its own
        under the conversation. `drawer` is held on the panel for that reason;
        see the note where it is declared.
    --}}
    <div
        x-show="drawer"
        x-cloak
        class="flex max-h-56 shrink-0 flex-col gap-3 overflow-y-auto border-t border-zinc-200 p-3 dark:border-zinc-700"
    >
            @error('party')
                <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
            @enderror

            {{--
                Faces beside names, fetched from each person's own server.

                Flux draws initials when there is no `src`, which is the same
                fallback the row of circles makes and for the same reason: most
                domiciles publish nothing, and a party where three people are
                letters and one is a picture is the ordinary case rather than a
                half-finished one.

                `initials:single` because a handle is not a name. Flux reads a
                string with no spaces in it as one word and takes two letters
                from it — `alice.home.test` becomes "Al" — which is right for a
                person called Alice Smith and wrong for an address. One letter
                is what everything else placeholding for a picture does, and it
                is what the circles beside this already draw.
            --}}
            <div class="flex flex-col gap-1">
                @foreach ($this->roster as $member)
                    <div class="flex items-center justify-between gap-2" wire:key="with-{{ $member->did }}">
                        <div class="flex min-w-0 items-center gap-2">
                            <flux:avatar
                                size="xs"
                                circle
                                :src="\StreetMesh\Protocol\PublishedAvatar::iconAt($member->handle)"
                                :name="$member->handle"
                                initials:single
                            />

                            <flux:text class="truncate">{{ $member->handle }}</flux:text>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($this->full)
                <flux:text size="sm">{{ __('This party is as big as it can be.') }}</flux:text>
            @else
                <flux:separator />

                <div class="flex flex-col gap-2">
                    <flux:text size="sm">{{ __('Anybody can join with this word:') }}</flux:text>

                    <div
                        class="flex items-center gap-2"
                        x-data="{
                            copied: false,

                            /*
                                Read off the page rather than captured.

                                `x-data` runs once, when this element is made,
                                so a word baked into it here stays the word it
                                was then — and pressing New word morphs a fresh
                                one into the span while the button goes on
                                copying the old one. The span is what Livewire
                                keeps current, so the span is what is asked.
                            */
                            copy () {
                                const word = this.$refs.word?.textContent.trim()

                                if (! word) {
                                    return
                                }

                                const said = () => {
                                    this.copied = true

                                    setTimeout(() => (this.copied = false), 1500)
                                }

                                if (navigator.clipboard?.writeText) {
                                    navigator.clipboard.writeText(word).then(said).catch(() => this.select())

                                    return
                                }

                                this.select()
                            },

                            /*
                                A browser that will not give us the clipboard
                                gets the word selected instead, which is one
                                keystroke rather than a button that did nothing.
                            */
                            select () {
                                const range = document.createRange()

                                range.selectNodeContents(this.$refs.word)

                                const selection = window.getSelection()

                                selection.removeAllRanges()
                                selection.addRange(range)
                            },
                        }"
                    >
                        <span
                            x-ref="word"
                            class="font-mono text-lg tracking-widest text-zinc-900 dark:text-white"
                        >{{ $this->party->code }}</span>

                        <flux:button size="xs" variant="subtle" @click="copy()">
                            <span x-text="copied ? '{{ __('Copied!') }}' : '{{ __('Copy') }}'"></span>
                        </flux:button>

                        <flux:button size="xs" variant="subtle" wire:click="rotate">{{ __('New word') }}</flux:button>
                    </div>

                    @if ($this->here->isNotEmpty())
                        <form wire:submit="invite" class="flex gap-2">
                            <flux:select wire:model="inviting" size="sm" class="flex-1">
                                <flux:select.option value="">{{ __('Or ask somebody here…') }}</flux:select.option>

                                @foreach ($this->here as $person)
                                    <flux:select.option value="{{ $person->did }}">{{ $person->handle }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:button size="sm" type="submit">{{ __('Ask') }}</flux:button>
                        </form>
                    @endif
                </div>
            @endif

        <flux:separator />

        {{--
            The way out, kept in here with everything else about the party
            rather than on show. Leaving is rare and irreversible-ish; the
            things beside it are the ones somebody opened this to do.

            Asks before it acts, the same way resigning a game does — one press
            to say so, a second to mean it. Clicking away is a change of mind
            rather than a commitment.
        --}}
        <div class="flex items-center justify-between gap-2">
            <flux:text size="sm">
                {{ trans_choice('Party of :count|Party of :count', $this->roster->count(), ['count' => $this->roster->count()]) }}
            </flux:text>

            <div class="flex items-center gap-2">
                {{--
                    A button that looks like one. It was styled as text, which
                    reads as a label somebody has coloured in rather than
                    something to press — and the one thing in here that ends
                    something should not be the one thing that looks inert.

                    Asks before it acts, the same way resigning a game does —
                    one press to say so, a second to mean it. Clicking away is a
                    change of mind rather than a commitment, and the colour
                    changes so the second press is visibly not the first.
                --}}
                <span x-data="{ asking: false }" @click.outside="asking = false">
                    <flux:button
                        size="sm"
                        ::variant="asking ? 'danger' : 'filled'"
                        @click="asking ? (asking = false, $wire.leave()) : asking = true"
                    >
                        <span x-text="asking ? '{{ __('Really leave?') }}' : '{{ __('Leave party') }}'"></span>
                    </flux:button>
                </span>

                {{--
                    And the way to put the drawer back, beside the thing it is
                    most likely to be sitting under.

                    It points the way the drawer will move. Opening it is done
                    from the row of buttons below; shutting it can be done from
                    either, because the hand is already here.
                --}}
                <flux:button
                    size="sm"
                    variant="subtle"
                    icon="chevron-down"
                    icon:variant="micro"
                    x-on:click="fold(false)"
                    aria-label="{{ __('Hide who is here') }}"
                />
            </div>
        </div>
    </div>
@endif
