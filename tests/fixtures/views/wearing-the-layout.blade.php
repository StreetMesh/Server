{{--
    An ordinary screen in this application's layout.

    Stands in for an experience's page, which is all an experience's page is —
    a Livewire component rendered into the same chrome. Written here so the
    claim being tested does not depend on which packages happen to be
    installed.
--}}
<x-layouts::app :title="__('Somewhere else')">
    <p>Somewhere else entirely.</p>
</x-layouts::app>
