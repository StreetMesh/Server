{{--
    The home page: what somebody signed in sees.

    The one surface where installed capabilities genuinely overlap, so it is a
    collection of panels rather than a page any of them owns. Which panels, and
    in what order, is the operator's decision — see streetmesh.home_page.
--}}
<x-layouts::app :title="__('Home')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        @forelse ($widgets as $widget)
            <flux:card>
                <flux:heading size="lg">{{ $widget->title() }}</flux:heading>

                <div class="mt-3">
                    @include($widget->view(), $widget->data())
                </div>
            </flux:card>
        @empty
            <flux:callout icon="squares-2x2">
                <flux:callout.heading>{{ __('Nothing is arranged here yet') }}</flux:callout.heading>
                <flux:callout.text>
                    {{ __('Capabilities offer panels for this page. Arrange them in config/streetmesh.php.') }}
                </flux:callout.text>
            </flux:callout>
        @endforelse
    </div>
</x-layouts::app>
