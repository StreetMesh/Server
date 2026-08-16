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
    {{-- The conversation, filling everything the strip below does not take.
         Text layers where voice supersedes: somebody cut off from the room's
         chat would miss whatever everybody around them is reacting to. --}}
    @livewire('venue::chat', [
        'space' => $this->party->room(),
        'placeholder' => __('Say something to your party'),
    ], key('party-'.$this->party->key))

    <div
        class="shrink-0 border-t border-zinc-200 dark:border-zinc-700"
        x-data="{ open: @js($detailsOpen) }"
    >
        {{-- The drawer, which opens upward over the conversation. Shut by
             default: this is reference rather than reading. --}}
        <div
            x-show="open"
            x-cloak
            class="flex max-h-56 flex-col gap-3 overflow-y-auto border-b border-zinc-200 p-3 dark:border-zinc-700"
        >
            @error('party')
                <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
            @enderror

            <div class="flex flex-col gap-1">
                @foreach ($this->roster as $member)
                    <div class="flex items-center justify-between" wire:key="with-{{ $member->did }}">
                        <flux:text>{{ $member->handle }}</flux:text>
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
        </div>

        {{-- And the strip itself, which is what you see when it is shut. --}}
        <div class="flex items-center gap-2 px-3 py-2">
            <flux:text class="flex-1" size="sm">
                {{ trans_choice('Party of :count|Party of :count', $this->roster->count(), ['count' => $this->roster->count()]) }}
            </flux:text>

            {{--
                Asks before it acts, the same way resigning a game does — one
                press to say so, a second to mean it. Clicking away is a change
                of mind rather than a commitment.
            --}}
            <span x-data="{ asking: false }" @click.outside="asking = false">
                <flux:button
                    size="xs"
                    variant="subtle"
                    @click="asking ? (asking = false, $wire.leave()) : asking = true"
                    x-bind:class="asking ? 'text-rose-600 dark:text-rose-400' : ''"
                >
                    <span x-text="asking ? '{{ __('Really leave?') }}' : '{{ __('Leave party') }}'"></span>
                </flux:button>
            </span>

            {{--
                A caret rather than a gear, because this is not settings — it is
                the rest of the strip, folded away. It points the way the drawer
                will move: up to open, since it opens upward over the
                conversation, and down to put it back.

                Both are drawn and one is hidden, because `flux:button`'s own
                `icon` is resolved when the page is built and cannot follow a
                state the browser is holding.
            --}}
            <flux:button
                size="xs"
                variant="subtle"
                x-on:click="open = ! open"
                x-bind:aria-expanded="open"
                x-bind:aria-label="open ? '{{ __('Hide party details') }}' : '{{ __('Show party details') }}'"
            >
                <flux:icon.chevron-up class="size-4" x-show="! open" />
                <flux:icon.chevron-down class="size-4" x-show="open" x-cloak />
            </flux:button>
        </div>
    </div>
@endif
