{{--
    The home page: what somebody signed in sees.

    The one surface where installed capabilities genuinely overlap, so it is a
    collection of panels rather than a page any of them owns. Which panels, and
    in what order, is the operator's decision — see config/streetmesh.php.
--}}
<x-streetmesh.shell :identity="$identity" :navigation="$navigation" title="Home">
    <h1>Home</h1>

    @forelse ($widgets as $widget)
        @include($widget->view(), $widget->data())
    @empty
        <p>Nothing is arranged here yet.</p>
    @endforelse
</x-streetmesh.shell>
