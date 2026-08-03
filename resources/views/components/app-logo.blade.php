@props([
    'sidebar' => false,
])

{{--
    The mark itself, rather than a one-colour glyph set into a coloured tile.

    The tile was the starter kit's, and it inverted with the theme: in dark mode
    it became a white chip with a black mark on it — a bright hole in an
    otherwise dark sidebar. The mark needs no tile. It is already a circular
    object that carries its own ground.
--}}
@if($sidebar)
    <flux:sidebar.brand :name="config('app.name', 'StreetMesh')" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center">
            <x-app-mark size="size-8" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="config('app.name', 'StreetMesh')" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center">
            <x-app-mark size="size-8" />
        </x-slot>
    </flux:brand>
@endif
