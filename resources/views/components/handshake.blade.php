@props([
    'here',
    'there',
])

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
    <div class="flex flex-1 flex-col items-center gap-2 text-center">
        <x-app-mark size="size-10" />
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
            A shop, not a server rack. A venue is a place somebody visits, and
            the metaphor the whole model is built on is worth keeping on the one
            screen where somebody is deciding whether to walk in.
        --}}
        <div class="flex size-10 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
            <flux:icon icon="building-storefront" class="size-6 text-zinc-500 dark:text-zinc-400" aria-hidden="true" />
        </div>
        <flux:text size="sm" class="break-all">{{ $there }}</flux:text>
    </div>
</div>
