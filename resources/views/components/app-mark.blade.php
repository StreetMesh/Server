@props([
    'size' => 'size-8',
    'onDark' => false,
])

{{--
    The StreetMesh mark, in colour.

    Two files rather than one, because the mark carries its own ground: the
    default is drawn on near-black, which reads on a pale surface and becomes a
    black disc on a dark one. The dark variant drops the ground so the streets
    go transparent and the page shows through, and it wants a surface no lighter
    than about #2A2A2A.

    `onDark` is for a panel that is dark whichever theme is on — the split auth
    layout paints one — where following the theme would be the wrong question to
    ask.

    The small variant throughout, because every caller renders this between 32
    and 48px. Below 32 the roundabout islands stop reading and the mono mark is
    the right tool instead.

    alt is empty on purpose: every caller puts the name beside it or is itself a
    link that says where it goes.
--}}
@if ($onDark)
    <img src="{{ asset('brand/streetmesh-mark-dark-small.svg') }}" alt="" class="{{ $size }}" />
@else
    <img src="{{ asset('brand/streetmesh-mark-small.svg') }}" alt="" class="{{ $size }} dark:hidden" />
    <img src="{{ asset('brand/streetmesh-mark-dark-small.svg') }}" alt="" class="hidden {{ $size }} dark:block" />
@endif
