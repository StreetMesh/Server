{{--
    What is inside the account menu, wherever the account menu is.

    There are two of them — one in the sidebar on a wide screen, one in the
    header on a narrow one — and they were two copies of the same markup. They
    drifted the moment anything was added to one: "Revoke access" appeared in
    the sidebar and not in the header, so on a phone there was no way to give a
    permission back at all.

    One file, used twice. Neither dropdown decides what is in the menu.
--}}
<div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
    <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />

    <div class="grid flex-1 text-start text-sm leading-tight">
        <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
        <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
    </div>
</div>

{{--
    Who you are here as, when that is a different question.

    A venue knows you by a permission your own server gave it, not by an
    account — so on a server that is both a domicile and a venue, the same
    person has two identities at once. They belong in one place rather than
    competing, and this is the place people already look.

    Absent entirely on a server with no venue, and on a venue nobody has
    arrived at.

    Guarded on there being a session at all, because this menu is on every page
    and asking who is visiting is a session question. Rendered anywhere without
    one — a console command, an error page — it would otherwise take the whole
    page down with it.
--}}
@if (request()->hasSession() && ($visiting = app(\StreetMesh\Venue\Visitors::class)->current(request())))
    <flux:menu.separator />

    <div class="px-1 py-1.5 text-start text-sm">
        {{--
            Names the venue and then hands off to the address underneath it:
            "Visiting server.test as" / "alice.home.test". One sentence broken
            across two lines rather than a label and an unrelated value.
        --}}
        <flux:text class="text-xs">
            {{ __('Visiting :venue as', ['venue' => config('streetmesh.host') ?? config('app.name')]) }}
        </flux:text>
        <flux:heading class="truncate">{{ $visiting->handle }}</flux:heading>
    </div>

    {{--
        "Revoke", not "Leave", and next to "Log out" on purpose — they are
        neighbours and must not read as the same act. Logging out ends a session
        on this server; this gives back a permission somebody else's server
        issued, and the venue drops its token when it does.
    --}}
    <form method="POST" action="{{ route('venue.leave') }}" class="w-full">
        @csrf
        <flux:menu.item as="button" type="submit" icon="key" class="w-full cursor-pointer">
            {{ __('Revoke access') }}
        </flux:menu.item>
    </form>
@endif

<flux:menu.separator />

<flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
    {{ __('Settings') }}
</flux:menu.item>

<form method="POST" action="{{ route('logout') }}" class="w-full">
    @csrf
    <flux:menu.item
        as="button"
        type="submit"
        icon="arrow-right-start-on-rectangle"
        class="w-full cursor-pointer"
        data-test="logout-button"
    >
        {{ __('Log out') }}
    </flux:menu.item>
</form>
