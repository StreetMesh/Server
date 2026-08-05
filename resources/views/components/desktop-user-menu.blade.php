<flux:dropdown position="bottom" align="start">
    <flux:sidebar.profile
        :name="auth()->user()->name"
        :initials="auth()->user()->initials()"
        icon:trailing="chevrons-up-down"
        data-test="sidebar-menu-button"
    />

    <flux:menu>
        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
            <flux:avatar
                :name="auth()->user()->name"
                :initials="auth()->user()->initials()"
            />
            <div class="grid flex-1 text-start text-sm leading-tight">
                <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
            </div>
        </div>
        {{--
            Who you are here as, when that is a different question.

            A venue knows you by a permission your own server gave it, not by
            an account — so on a server that is both a domicile and a venue,
            the same person has two identities on screen at once. They belong
            in one place rather than competing, and this is the place people
            already look.

            Absent entirely on a server with no venue, and on a venue nobody
            has arrived at.
        --}}
        @if ($visiting = app(\StreetMesh\Venue\Visitors::class)->current(request()))
            <flux:menu.separator />

            <div class="px-1 py-1.5 text-start text-sm">
                {{--
                    Names the venue rather than the act. "Visiting as" told
                    somebody what to call themselves, which they knew; this
                    tells them where they are, which on a network of servers
                    that all look alike is the part worth saying.

                    The host rather than the application's own name, because it
                    is what a handle is built from and what somebody would type
                    to come back.
                --}}
                <flux:text class="text-xs">
                    {{ __('Visiting :venue', ['venue' => config('streetmesh.host') ?? config('app.name')]) }}
                </flux:text>
                <flux:heading class="truncate">{{ $visiting->handle }}</flux:heading>
            </div>

            {{--
                "Revoke", not "Leave", and next to "Log out" on purpose — they
                are neighbours and must not read as the same act. Logging out
                ends a session on this server; this gives back a permission
                somebody else's server issued.
            --}}
            <form method="POST" action="{{ route('venue.leave') }}" class="w-full">
                @csrf
                <flux:menu.item
                    as="button"
                    type="submit"
                    icon="key"
                    class="w-full cursor-pointer"
                >
                    {{ __('Revoke access') }}
                </flux:menu.item>
            </form>
        @endif

        <flux:menu.separator />
        <flux:menu.radio.group>
            <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                {{ __('Settings') }}
            </flux:menu.item>
            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <flux:menu.item
                    as="button"
                    type="submit"
                    icon="arrow-right-start-on-rectangle"
                    class="w-full cursor-pointer"
                    data-test="logout-button"
                >
                    {{ __('Log out') }}
                </flux:menu.item>
            </form>
        </flux:menu.radio.group>
    </flux:menu>
</flux:dropdown>
