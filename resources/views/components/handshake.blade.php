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
            The venue's own mark when the venue is this server, and a shop front
            when it is somebody else's.

            A blended server asking itself for permission is the ordinary case
            here and the one that looked wrong: both sides said `server.test`,
            and the half that has a name and a mark of its own was drawn as an
            anonymous glyph. Its mark is not a stranger's — it is configured
            three lines away, in this same container.

            Anywhere else the glyph stays, and for the reason it always had:
            there is no way to know what a server across the network looks like,
            and fetching a stranger's image into a permission screen is a
            request to somebody else's host at the exact moment a page must not
            be making one.
        --}}
        @if ($ours)
            <x-app-mark size="size-10" for="venue" />
        @else
            <div class="flex size-10 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                <flux:icon icon="building-storefront" class="size-6 text-zinc-500 dark:text-zinc-400" aria-hidden="true" />
            </div>
        @endif

        <flux:text size="sm" class="break-all">{{ $there }}</flux:text>
    </div>
</div>
