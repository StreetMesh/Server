{{--
    The front page: what a stranger sees at the root.

    There is one root, so a server offering more than one capability says which
    greets people — see streetmesh.front_page. Capabilities offer a view for
    this; none of them claims it.
--}}
<x-layouts::auth.simple :title="$identity->handle">
    <div class="flex w-full flex-col gap-6">
        <div class="flex flex-col gap-2 text-center">
            <flux:heading size="xl">{{ $identity->handle }}</flux:heading>
            <flux:text class="font-mono text-xs">{{ $identity->did }}</flux:text>
        </div>

        @if ($front !== null)
            @include($front)
        @else
            <flux:text class="text-center">A StreetMesh server.</flux:text>
        @endif

        {{--
            The way in, from whichever capability greets people.

            Not `login`. That is a domicile's door — it holds accounts and the
            person arriving has one. A venue holds no accounts at all: somebody
            turns up with an address their own server issued, so the door is a
            box to type an address into. Hard-coded here, a venue sent every
            visitor to a login form for a server they could never have an
            account on.
        --}}
        <div class="flex justify-center gap-3">
            @auth
                <flux:button :href="route('dashboard')" variant="primary" wire:navigate>{{ __('Home') }}</flux:button>
            @elseif ($action !== null)
                <flux:button :href="route($action['route'])" variant="primary" wire:navigate>
                    {{ __($action['label']) }}
                </flux:button>
            @endauth
        </div>
    </div>
</x-layouts::auth.simple>
