{{--
    Who is here, and the way in or out, are questions only the installed
    capabilities can answer — a domicile means a resident with an account, a
    venue means a visitor holding permission from somewhere else, and the
    framework's `auth()` can see only the first.
--}}
@inject('capabilities', 'StreetMesh\Protocol\Laravel\Capabilities\Capabilities')

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Home') }}
                    </flux:sidebar.item>

                    {{--
                        Whatever is installed, in the order it registered. A
                        capability contributes here without knowing what else is
                        present, and without deciding what the frame looks like.
                    --}}
                    @foreach (app(\StreetMesh\Protocol\Laravel\Capabilities\Capabilities::class)->navigation() as $item)
                        <flux:sidebar.item
                            :icon="$item['icon'] ?? 'squares-2x2'"
                            :href="route($item['route'])"
                            :current="request()->routeIs($item['route'])"
                            wire:navigate
                        >
                            {{ __($item['label']) }}
                        </flux:sidebar.item>
                    @endforeach
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="folder-git-2" href="https://github.com/laravel/livewire-starter-kit" target="_blank">
                    {{ __('Repository') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="book-open-text" href="https://laravel.com/docs/starter-kits#livewire" target="_blank">
                    {{ __('Documentation') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            {{--
                Whoever is here, asked of the capabilities rather than of
                `auth()`.

                A venue's visitor is not signed in — they hold permission from
                another server, and the framework knows nothing about them. This
                corner used to check `auth()`, decide nobody was there, and
                offer a "Log in" link to somebody already using the place, for
                an account that server cannot issue.

                And when nobody is here, the way in is the one that capability
                actually has: a login form at a domicile, an address box at a
                venue.
            --}}
            @php($whoever = $capabilities->whoever())

            @if ($whoever !== null)
                <x-desktop-user-menu
                    class="hidden lg:block"
                    :name="$whoever['name']"
                    :leave="$whoever['leave']"
                />
            @elseif ($capabilities->frontAction(config('streetmesh.front_page')) !== null)
                @php($in = $capabilities->frontAction(config('streetmesh.front_page')))

                <flux:sidebar.nav class="hidden lg:block">
                    <flux:sidebar.item icon="arrow-right-end-on-rectangle" :href="route($in['route'])" wire:navigate>
                        {{ __($in['label']) }}
                    </flux:sidebar.item>
                </flux:sidebar.nav>
            @endif
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            @if ($whoever === null && ($in = $capabilities->frontAction(config('streetmesh.front_page'))) !== null)
                <flux:button :href="route($in['route'])" size="sm" variant="ghost" wire:navigate>
                    {{ __($in['label']) }}
                </flux:button>
            @endif

            @if ($whoever !== null)
                <flux:dropdown position="top" align="end">
                    <flux:profile
                        :initials="auth()->user()?->initials() ?? Str::upper(Str::substr($whoever['name'], 0, 2))"
                        icon-trailing="chevron-down"
                    />

                    {{-- The same menu as the sidebar's, because it is the same menu. --}}
                    <flux:menu>
                        <x-user-menu-items :leave="$whoever['leave']" />
                    </flux:menu>
                </flux:dropdown>
            @endif
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
