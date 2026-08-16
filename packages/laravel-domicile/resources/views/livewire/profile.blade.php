<?php

use Livewire\Attributes\Title;
use Livewire\Component;
use StreetMesh\Protocol\Laravel\Identity\Identities;
use StreetMesh\Protocol\Laravel\Identity\Identity;

new class extends Component
{
    public string $handle = '';

    public function mount(string $handle): void
    {
        $this->handle = strtolower(trim($handle));
    }

    /**
     * Whoever this page is about, or nobody.
     *
     * Only residents. The server has an identity of its own and it is not a
     * person — asking for it by name should find nothing here rather than a
     * profile for a machine.
     */
    public function resident(): ?Identity
    {
        $identity = app(Identities::class)->byHandle($this->handle);

        return $identity?->is_server ? null : $identity;
    }

    public function title(): string
    {
        return $this->resident()?->handle ?? __('Nobody here');
    }
};?>

{{--
    One resident, at an address anybody can link to.

    This is where `collegeman.stme.sh` sends somebody. That name is a hostname,
    and a hostname is for machines resolving a handle — a person typing it into
    a browser is asking about a person, and gets sent here.
--}}
<div class="flex flex-col gap-6">
    @php($resident = $this->resident())

    @if ($resident === null)
        <flux:callout icon="user-group">
            <flux:callout.heading>{{ __('Nobody here') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Nobody at this server goes by that name.') }}
            </flux:callout.text>
            <x-slot name="actions">
                <flux:button :href="route('domicile.directory')" size="sm" variant="outline" wire:navigate>
                    {{ __('Directory') }}
                </flux:button>
            </x-slot>
        </flux:callout>
    @else
        <flux:heading size="xl">{{ $resident->handle }}</flux:heading>

        {{--
            The name and the identifier, together, because they are two
            different things and the difference is the point of the exercise.

            The name is how anybody reaches them and can be changed. The
            identifier is who they are and cannot — it is what every record they
            ever sign is signed as, and what somebody holding one of those
            records years from now resolves.
        --}}
        <flux:card class="flex flex-col gap-4">
            <div class="flex flex-col gap-1">
                <flux:text class="text-xs uppercase">{{ __('Address') }}</flux:text>
                <flux:text class="font-mono break-all">{{ $resident->handle }}</flux:text>
            </div>

            <div class="flex flex-col gap-1">
                <flux:text class="text-xs uppercase">{{ __('Identifier') }}</flux:text>
                <flux:text class="font-mono text-xs break-all">{{ $resident->did }}</flux:text>
            </div>
        </flux:card>
    @endif
</div>
