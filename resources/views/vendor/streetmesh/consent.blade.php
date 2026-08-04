{{--
    This server's own consent screen, overriding the protocol package's.

    The package ships a plain standalone page because it has no business
    deciding what a domicile looks like. This is a domicile, and it does: the
    same layout, width and controls as logging in, because being asked to
    approve something is the same kind of moment and should not arrive looking
    like a different website.

    What must survive any version of this screen, and does: the venue is named,
    the request is described in words rather than in scope strings, and refusing
    is exactly as easy as agreeing.
--}}
<x-layouts::auth :title="__('Permission')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__(':venue would like permission', ['venue' => $venue])"
            :description="__('You can take this back at any time.')"
        />

        {{--
            What is being asked for, in sentences the package wrote from the
            scopes. Not the scope strings themselves: `repo:com.streetmesh.
            games.chess?action=create` is precise and tells somebody deciding
            nothing at all.
        --}}
        <flux:callout icon="key">
            <flux:callout.heading>{{ __('It is asking to') }}</flux:callout.heading>
            <flux:callout.text>
                <ul class="list-disc ps-4">
                    @foreach ($asking as $sentence)
                        <li>{{ $sentence }}</li>
                    @endforeach
                </ul>
            </flux:callout.text>
        </flux:callout>

        {{--
            Two answers of equal weight. "Allow" is the primary because it is
            what most people are here to do, not because it is what this server
            would prefer — "Not now" is a full-width button beside it rather
            than a link tucked underneath.
        --}}
        <form method="POST" action="{{ route('streetmesh.oauth.approve') }}" class="flex gap-3">
            @csrf
            <input type="hidden" name="request_uri" value="{{ $permission->request_uri }}">

            <flux:button type="submit" name="answer" value="yes" variant="primary" class="w-full">
                {{ __('Allow') }}
            </flux:button>

            <flux:button type="submit" name="answer" value="no" class="w-full">
                {{ __('Not now') }}
            </flux:button>
        </form>
    </div>
</x-layouts::auth>
