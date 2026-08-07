@props([
    'name' => null,
    'leave' => null,
])

{{--
    Whoever is here, in the corner of the sidebar.

    The name is passed in rather than read from `auth()`, because on a venue
    there is no account to read: a visitor is known by the address their own
    server issued, and the framework has never heard of them. This used to reach
    for `auth()->user()->name` and could therefore only be rendered by a
    domicile.
--}}
@php
    $who = $name ?? auth()->user()?->name ?? '';
    $initials = auth()->user()?->initials() ?? Str::upper(Str::substr($who, 0, 2));
@endphp

<flux:dropdown position="bottom" align="start">
    <flux:sidebar.profile
        :name="$who"
        :initials="$initials"
        icon:trailing="chevrons-up-down"
        data-test="sidebar-menu-button"
    />

    {{-- Contents live in one place, shared with the header's menu. --}}
    <flux:menu>
        <x-user-menu-items :leave="$leave" />
    </flux:menu>
</flux:dropdown>
