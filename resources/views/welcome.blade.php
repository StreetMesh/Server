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

        <div class="flex justify-center gap-3">
            @auth
                <flux:button :href="route('dashboard')" variant="primary" wire:navigate>{{ __('Home') }}</flux:button>
            @else
                <flux:button :href="route('login')" variant="primary" wire:navigate>{{ __('Sign in') }}</flux:button>
            @endauth
        </div>
    </div>
</x-layouts::auth.simple>
