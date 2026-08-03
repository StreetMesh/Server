@props([
    'sidebar' => false,
])

{{--
    The mark itself, rather than a one-colour glyph set into a coloured tile.

    The tile was the starter kit's, and it inverted with the theme: in dark mode
    it became a white chip with a black mark on it — a bright hole in an
    otherwise dark sidebar. The mark needs no tile. It is already a circular
    object that carries its own ground.

    Two files, because that ground is the whole problem. The default mark is
    drawn on near-black, which reads on a light sidebar and becomes a black disc
    on a dark one. The dark variant drops the ground so the streets go
    transparent and the sidebar shows through; it wants a surface no lighter
    than about #2A2A2A, which bg-zinc-900 comfortably is.

    The small variant, because this renders at 32px and the primary mark is
    drawn for 48px and up.

    alt is empty on purpose — the name is right beside it, and a logo that
    announces itself twice is worse than one that says nothing.
--}}
@if($sidebar)
    <flux:sidebar.brand :name="config('app.name', 'StreetMesh')" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center">
            <img src="{{ asset('brand/streetmesh-mark-small.svg') }}" alt="" class="size-8 dark:hidden" />
            <img src="{{ asset('brand/streetmesh-mark-dark-small.svg') }}" alt="" class="hidden size-8 dark:block" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="config('app.name', 'StreetMesh')" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center">
            <img src="{{ asset('brand/streetmesh-mark-small.svg') }}" alt="" class="size-8 dark:hidden" />
            <img src="{{ asset('brand/streetmesh-mark-dark-small.svg') }}" alt="" class="hidden size-8 dark:block" />
        </x-slot>
    </flux:brand>
@endif
