@props([
    'here',
    'there',
])

{{--
    Whether the venue asking is this same server.

    Compared on the host rather than assumed from anything adjacent, and folded
    and trimmed because one of these comes out of configuration and the other
    out of a request. A stray capital would draw two strangers where there is
    one server.
--}}
@php($ours = strcasecmp(trim($here), trim($there)) === 0)

{{--
    Where the other server publishes its face, built from its name.

    Nothing is fetched here to find this out — the address is a convention every
    venue serves, so a hostname is enough. Null when what arrived is not a
    hostname at all, which is the one case that must not turn into an address
    somebody else chose.
--}}
@php($published = $ours ? null : \StreetMesh\Protocol\PublishedMark::at($there))

{{--
    Two servers, about to know each other.

    The moment this heads is the only one in StreetMesh where two servers are
    introduced, and a single mark over a single name — which is what every other
    screen wears — says the opposite of what is happening. Somebody looking at a
    consent screen needs to see that there are two parties and that they are not
    the same party, before reading a word.

    Arrows both ways rather than one, because this is not a handover. The venue
    will ask this server for things and this server will answer, for as long as
    the permission stands; a single arrow would draw a one-time send.

    Only the local side has a mark. There is no way to know what a server across
    the network looks like, and the honest options are a generic glyph or
    fetching a stranger's image into a permission screen — which is a request to
    somebody else's host at the exact moment a page must not be doing that.
--}}
<div {{ $attributes->class(['flex items-center gap-4']) }}>
    {{--
        The domicile's mark, not the venue's.

        This screen is a domicile being asked for permission by a venue
        somewhere else, and on a blended server the venue in the same container
        has nothing to do with it. Wearing the sign over the games room here
        would say the two parties are one party, which is the single thing this
        component exists to deny.
    --}}
    <div class="flex flex-1 flex-col items-center gap-2 text-center">
        <x-app-mark size="size-10" for="domicile" />
        <flux:text size="sm" class="break-all">{{ $here }}</flux:text>
    </div>

    {{--
        Nudged up by the height of the label below it, so it sits level with
        the two marks rather than with the whole stacked column.
    --}}
    <flux:icon
        icon="arrows-right-left"
        variant="mini"
        class="mb-7 size-5 shrink-0 text-zinc-400 dark:text-zinc-500"
        aria-hidden="true"
    />

    <div class="flex flex-1 flex-col items-center gap-2 text-center">
        {{--
            The venue's own mark, from the venue's own origin.

            Read straight from the host it describes rather than repeated by
            this server, and that is the point rather than a shortcut: only
            `tabletop.streetmesh.com` can put a picture at
            `tabletop.streetmesh.com`, so what somebody is looking at is the
            venue itself and not this server's account of it. A domicile serving
            a copy would be vouching for a likeness it has no way to check.

            It costs a request from the reader's browser to the venue, which is
            a real thing to weigh on a screen about permission — the venue
            learns somebody reached it, including somebody who then refuses. It
            is worth it here. The alternative is a page that asks "do you trust
            this place?" beside a picture of nowhere in particular.

            When the venue is this same server there is nothing to fetch: it is
            configured in this container, three lines away.
        --}}
        @if ($ours)
            <x-app-mark size="size-10" for="venue" />
        @elseif ($published !== null)
            {{--
                A server that publishes no mark is not broken, and a broken
                image would be a worse answer than the glyph it replaced — so
                failing to load puts the glyph back rather than leaving a torn
                page behind. Alpine rather than an `onerror` attribute, because
                the fallback is a sibling element and not a second address to
                try.
            --}}
            <div x-data="{ shown: true }" class="flex size-10 items-center justify-center">
                <img
                    src="{{ $published['light'] }}"
                    alt=""
                    class="size-10 dark:hidden"
                    x-show="shown"
                    x-on:error="shown = false"
                />
                <img
                    src="{{ $published['dark'] }}"
                    alt=""
                    class="hidden size-10 dark:block"
                    x-show="shown"
                    x-on:error="shown = false"
                />

                <div x-show="!shown" x-cloak class="flex size-10 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <flux:icon icon="building-storefront" class="size-6 text-zinc-500 dark:text-zinc-400" aria-hidden="true" />
                </div>
            </div>
        @else
            <div class="flex size-10 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                <flux:icon icon="building-storefront" class="size-6 text-zinc-500 dark:text-zinc-400" aria-hidden="true" />
            </div>
        @endif

        <flux:text size="sm" class="break-all">{{ $there }}</flux:text>
    </div>
</div>
