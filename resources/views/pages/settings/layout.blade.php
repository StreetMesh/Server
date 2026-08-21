<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <flux:navlist aria-label="{{ __('Settings') }}">
            <flux:navlist.item :href="route('profile.edit')" wire:navigate>{{ __('Profile') }}</flux:navlist.item>
            <flux:navlist.item :href="route('security.edit')" wire:navigate>{{ __('Security') }}</flux:navlist.item>
            <flux:navlist.item :href="route('appearance.edit')" wire:navigate>{{ __('Appearance') }}</flux:navlist.item>

            {{--
                And whatever the installed capabilities want here.

                After the application's own rather than mixed in with them: these
                three are about the account, and what a capability adds is about
                what this server is. A venue adds nothing, because a visitor has
                no account here to configure.
            --}}
            @foreach (app(\StreetMesh\Protocol\Laravel\Capabilities\Capabilities::class)->settings() as $item)
                <flux:navlist.item :href="route($item['route'])" wire:navigate>
                    {{ __($item['label']) }}
                </flux:navlist.item>
            @endforeach
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <flux:heading>{{ $heading ?? '' }}</flux:heading>
        <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

        <div class="mt-5 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
