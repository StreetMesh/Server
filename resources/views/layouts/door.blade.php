@props([
    'title' => null,

    /*
     * Which side the colour is on: `end` for the far side, `start` for the near
     * one. Two screens use this layout and they are two halves of one journey —
     * the venue's door hands somebody to their own server, and that server
     * hands them back. Flipping the panel between them is what makes the
     * handover legible: you can see you have arrived somewhere else, without
     * either screen having to say so.
     */
    'panel' => 'end',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    {{--
        The door, for somebody who does not live here.

        Its own layout rather than the auth one, because it is not the same
        moment. The auth layout frames signing in to this server; this frames
        arriving from a different one, and the two were only ever sharing a
        frame because both happen to be a form on an empty page.

        On a laptop that difference is worth the room: the column takes a
        comfortable width instead of being a narrow card marooned in the middle
        of a wide screen. Below `lg` the panel is gone and this is the centred
        column it always was, which is the right answer on a phone — there is no
        room to spend, and the form is the whole screen.
    --}}
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        {{--
            The content comes first in the markup whichever side it is drawn on,
            and the row reverses to move it. Ordering the DOM by appearance
            would put a decorative empty div ahead of the heading for anybody
            reading this without CSS, or with a screen reader.
        --}}
        <div @class([
            'flex min-h-svh',
            'lg:flex-row-reverse' => $panel === 'start',
        ])>
            <div class="flex flex-1 flex-col justify-center px-6 py-12 lg:flex-none lg:px-20 xl:px-24">
                <div class="mx-auto flex w-full max-w-sm flex-col gap-8 lg:w-96">
                    {{--
                        Which building this is, said before anything is asked.

                        Not a link home. Somebody standing here followed an
                        invitation to a particular thing, and a way out of the
                        page is not what they came for — on the auth layouts the
                        mark is a link because the person clicking it already
                        lives here and home means something to them.

                        A slot, because a screen that knows who both parties are
                        can say so, and this layout cannot know that. It is only
                        a default: Livewire's `#[Layout]` cannot pass a named
                        slot, so any screen framed that way gets this.
                    --}}
                    {{--
                        The venue's mark, because this door is the venue's.

                        Somebody standing here is arriving at the place with the
                        sign over it, not at the server underneath. On a blended
                        server those differ, and the same screen is reached from
                        the venue every time.
                    --}}
                    @if (isset($masthead))
                        {{ $masthead }}
                    @else
                        <div class="flex items-center gap-3">
                            <x-app-mark size="size-9" for="venue" />
                            <flux:heading size="lg">{{ config('app.name', 'StreetMesh') }}</flux:heading>
                        </div>
                    @endif

                    {{ $slot }}
                </div>
            </div>

            {{--
                Colour, and nothing in it.

                Not `bg-accent`, which is white here — accent flips per theme so
                that buttons read light-on-dark, and this page is always dark.
                A full-height white slab is what that would have painted.

                `w-0 flex-1` is what lets the content column size itself first
                and this take whatever is left, rather than the two splitting
                the screen evenly and the form growing slack on a wide monitor.

                The border goes on whichever edge faces the content, and that
                is not the same edge in both arrangements: `flex-row-reverse`
                changes what is drawn where, but `border-s`/`border-e` follow
                the writing direction and do not move with it. So each case
                names its own edge instead of one class covering both.
            --}}
            <div @class([
                'hidden w-0 flex-1 bg-neutral-800 lg:block',
                'border-s border-neutral-800' => $panel === 'end',
                'border-e border-neutral-800' => $panel === 'start',
            ]) aria-hidden="true"></div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts

        @include('streetmesh::unreachable')

        {{-- Talking to the people around you — see the note in the app layout.
             On every screen this server serves, because a badge that comes and
             goes with the layout is one people stop trusting. --}}
        @include('venue::comms.widget')

    </body>
</html>
