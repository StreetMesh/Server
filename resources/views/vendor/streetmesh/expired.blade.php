{{--
    This server's own version of "there is nothing here to answer".

    Wears the same frame as the consent screen it replaces, on the same side,
    because it stands in exactly that spot in the journey — somebody arrived
    expecting to decide something, and the thing to decide is gone. A different
    layout here would read as having been thrown somewhere else entirely.

    The wording blames nobody. Both ways of arriving — a screen left open past
    the few minutes a request lasts, and a reload after already deciding — are
    ordinary things for a person to do.
--}}
<x-layouts::door :title="__('Nothing to answer')" panel="start">
    <x-slot:masthead>
        {{--
            This server as a domicile, not as a venue. Whatever the venue half
            of this container is called, the screen somebody is standing on is
            the one that holds their records.
        --}}
        <div class="flex items-center gap-3">
            <x-app-mark size="size-9" for="domicile" />
            <flux:heading size="lg">{{ config('streetmesh.host') }}</flux:heading>
        </div>
    </x-slot:masthead>

    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-2">
            <flux:heading size="xl">{{ __('That request has expired') }}</flux:heading>

            {{--
                What did *not* happen, first. Somebody who walked away from a
                permission screen wants to know whether they accidentally
                granted something on their way out, and that answer should not
                be below the fold or implied.
            --}}
            <flux:subheading>
                {{ __('Nothing was shared and nothing was granted.') }}
            </flux:subheading>
        </div>

        <flux:text>
            {{ __('Requests last a few minutes. This one was left too long, or it has already been answered.') }}
        </flux:text>

        {{--
            A way onward rather than a dead end.

            Back to the venue where there is one to name — from there the person
            can ask again, which is the thing they were trying to do. The
            permission that knew where to send them is the very thing that has
            gone, so this comes from the client identifier on the request.

            Answering the form leaves no client identifier behind, so that path
            falls back to this server's front page, which is at least somewhere.
        --}}
        @if ($venue !== null)
            <flux:button :href="'https://'.$venue" variant="primary" class="w-full">
                {{ __('Back to :venue', ['venue' => $venue]) }}
            </flux:button>
        @else
            <flux:button :href="url('/')" variant="primary" class="w-full" wire:navigate>
                {{ __('Go to the front page') }}
            </flux:button>
        @endif
    </div>
</x-layouts::door>
