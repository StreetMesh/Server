@props([
    'size' => 'size-8',
    'onDark' => false,

    /*
     * Whose mark to draw: a capability's name, or nothing for the server's own.
     *
     * A server can be a domicile and a venue at once, and those are two things
     * to be rather than one thing wearing two hats. Somebody at the venue half
     * is somewhere called Tabletop; the same server answering for that person's
     * records is StreetMesh. A screen that belongs to one of them says so, and
     * shared chrome says nothing and gets the server's own.
     */
    'for' => null,
])

{{--
    Naming nobody gets the mark of whichever capability greets people, which is
    what this server is to somebody looking at it. The operator's preference
    decides it on a server that is more than one thing; a server that is only a
    venue needs no configuration at all.
--}}
@php($mark = app(\StreetMesh\Protocol\Laravel\Capabilities\Capabilities::class)
    ->mark($for, config('streetmesh.front_page')))

{{--
    A mark, in colour.

    Two files rather than one, because the mark carries its own ground: the
    default is drawn on near-black, which reads on a pale surface and becomes a
    black disc on a dark one. The dark variant drops the ground so the page
    shows through, and it wants a surface no lighter than about #2A2A2A.

    `onDark` is for a panel that is dark whichever theme is on — the split auth
    layout paints one — where following the theme would be the wrong question to
    ask.

    The small variant throughout, because every caller renders this between 32
    and 48px. Below 32 the packs ship a micro variant and nothing here is small
    enough to want one.

    alt is empty on purpose: every caller puts the name beside it or is itself a
    link that says where it goes.
--}}
@if ($onDark)
    <img src="{{ asset($mark->dark()) }}" alt="" class="{{ $size }}" />
@else
    <img src="{{ asset($mark->light()) }}" alt="" class="{{ $size }} dark:hidden" />
    <img src="{{ asset($mark->dark()) }}" alt="" class="hidden {{ $size }} dark:block" />
@endif
