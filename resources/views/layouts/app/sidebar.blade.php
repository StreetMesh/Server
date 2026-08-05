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
                Guarded, because this chrome is not only for people with an
                account here. A venue's screens are public by design — a visitor
                arrives holding a name issued somewhere else, and may be holding
                nothing at all — so a layout that reaches for `auth()->user()`
                unguarded is a layout that only a domicile can use.
            --}}
            @auth
                <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
            @else
                <flux:sidebar.nav class="hidden lg:block">
                    <flux:sidebar.item icon="arrow-right-end-on-rectangle" :href="route('login')" wire:navigate>
                        {{ __('Log in') }}
                    </flux:sidebar.item>
                </flux:sidebar.nav>
            @endauth
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            @guest
                <flux:button :href="route('login')" size="sm" variant="ghost" wire:navigate>
                    {{ __('Log in') }}
                </flux:button>
            @endguest

            @auth
            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                {{-- The same menu as the sidebar's, because it is the same menu. --}}
                <flux:menu>
                    <x-user-menu-items />
                </flux:menu>
            </flux:dropdown>
            @endauth
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
