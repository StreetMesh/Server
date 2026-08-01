{{--
    The front page: what a stranger sees at the root.

    There is one root, so a server offering more than one capability has to say
    which greets people. See streetmesh.front_page.
--}}
<x-streetmesh.shell :identity="$identity" :navigation="[]">
    @if ($front !== null)
        @include($front)
    @else
        <h1>{{ $identity->handle }}</h1>
        <p>A StreetMesh server.</p>
    @endif
</x-streetmesh.shell>
