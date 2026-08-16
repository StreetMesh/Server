{{--
    Which table this is, what is happening at it, and the way out.

    Inside the board's component rather than above it, because the middle of
    those three changes on its own — whose move it is, or an invitation while
    there is nobody to play. It used to sit outside and could only ever show the
    key.

    The key rather than "Chess": you know it is chess, you are looking at a
    chessboard. What a screen cannot tell you is which of several games you are
    in, and that is the thing somebody reads back to you.
--}}
<div class="flex w-full items-center justify-between gap-4">
    <flux:heading size="xl">
        {{ __('Game :key', ['key' => Str::of($game->key)->substr(-6)]) }}
    </flux:heading>

    <div class="flex items-center gap-3">
        {{--
            Playing a finished game back, beside its name rather than under the
            board. There is one control now: stepping through it a move at a
            time was a third and a fourth button for something nobody was asking
            to do that precisely.
        --}}
        <flux:button
            x-show="over"
            x-cloak
            size="sm"
            variant="outline"
            icon="play"
            @click="play()"
        >
            <span x-text="playing ? '{{ __('Pause') }}' : '{{ __('Replay') }}'"></span>
        </flux:button>

        @if ($game->isOpen())
            {{--
                One place, and something to do in it.

                A table whose socket has gone offers the way back. Otherwise it
                offers the table itself — as an invitation while there is a
                chair going, and as a plain share once the game is under way.

                It used to say whose turn it was here. That was a status line
                wearing an action's clothes: it sat where the only button is,
                said "Waiting for white" at somebody who could see perfectly
                well that white had not moved, and took the space from the one
                thing worth doing. Whose turn it is belongs to the board, which
                says it by the piece, and to the player rows, which say it in
                words for anybody who has asked for less movement.
            --}}
            <flux:button
                x-show="disconnected"
                x-cloak
                size="sm"
                variant="primary"
                icon="arrow-path"
                @click="reconnect()"
            >
                {{ __('Reconnect') }}
            </flux:button>

            {{--
                Handing somebody the table, in whichever way this device has.

                Where the system offers a share sheet, the button *is* the
                share: one press, and the sheet already knows the person's
                messages, their contacts and their other devices. Putting that
                behind a menu would be asking somebody to choose to be shown a
                chooser.

                Where there is no sheet — which in practice means a laptop —
                the two things that sheet would have done are the menu instead.

                Same word on the button either way, because it is the same act;
                only the reason for it changes, and that is what the label
                follows.
            --}}
            <flux:button
                x-show="!disconnected && canShare"
                x-cloak
                size="sm"
                variant="primary"
                icon="arrow-up-on-square"
                @click="share()"
            >
                <span x-text="waiting ? (invited || '{{ __('Invite opponent') }}') : '{{ __('Share') }}'"></span>
            </flux:button>

            <flux:dropdown x-show="!disconnected && !canShare" x-cloak position="bottom" align="end">
                <flux:button
                    size="sm"
                    variant="primary"
                    icon="arrow-up-on-square"
                    icon:trailing="chevron-down"
                >
                    <span x-text="waiting ? (invited || '{{ __('Invite opponent') }}') : '{{ __('Share') }}'"></span>
                </flux:button>

                <flux:menu>
                    <flux:menu.item icon="link" @click="copyLink()">
                        {{ __('Copy link') }}
                    </flux:menu.item>

                    {{--
                        For the person sitting across the room rather than
                        across the network. Two people at one table are often
                        two people in one place, and reading a URL out loud is
                        the worst way to hand somebody a game.
                    --}}
                    <flux:menu.item icon="qr-code" x-on:click="$flux.modal('chess-qr').show()">
                        {{ __('QR code') }}
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>

            {{--
                Offered beside what is happening, rather than instead of it.

                This used to replace the whole line above for anybody not
                seated, which was fine when only players had a live board —
                nobody else had anything to be told. Now that a passer-by
                watches, "not seated" is the ordinary state of somebody
                following the game, and swapping their status line for an
                invitation to take a chair told them nothing about the game
                they had come to watch.

                Only while there is a chair. A full game said "Sit to play" to
                every watcher, which is a promise the venue would have broken
                the moment they followed it — they would have gone through the
                door to be seated in the audience they were already in.
            --}}
            @if (! $this->seated() && count($this->players()) < 2)
                <flux:button
                    :href="route('chess.sit', $game->key)"
                    size="sm"
                    variant="primary"
                    icon="arrow-right-end-on-rectangle"
                    wire:navigate
                >
                    {{ __('Sit to play') }}
                </flux:button>
            @endif

            {{--
                The code itself, drawn once and kept out of the way.

                On a white card whatever the theme, because a reader looks for
                dark on light and an inverted code is one many cameras refuse.

                The code and nothing else. The address under it was there for a
                camera that would not focus, but a URL this long is not something
                anybody reads off a screen and types — and Copy link is one item
                up the same menu for anybody who wants the text of it.
            --}}
            <flux:modal name="chess-qr" class="max-w-sm">
                <div class="flex flex-col items-center gap-4">
                    <flux:heading size="lg">{{ __('Scan to open this game') }}</flux:heading>

                    <div class="rounded-lg bg-white p-3">
                        {!! $this->qr() !!}
                    </div>
                </div>
            </flux:modal>
        @endif

        {{--
            Hidden on a phone, where the board wants the width and the browser
            has a back button of its own an inch below this one.

            The public list for somebody who has not arrived. The lobby is
            behind the door, so the way out of a game a stranger was watching
            put them in front of a form asking them to name their own server.
        --}}
        <flux:button
            :href="route($this->arrived() ? 'chess.lobby' : 'chess.watch')"
            size="sm"
            variant="outline"
            icon:trailing="arrow-right"
            class="max-sm:hidden"
            wire:navigate
        >
            {{ __('Lobby') }}
        </flux:button>
    </div>
</div>
