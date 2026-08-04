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
            :description="__('You can revoke this at any time.')"
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
        {{--
            Answering takes a round trip to the venue's server, which is long
            enough to press again. Both buttons go dead on the first press so
            the second does nothing.

            Deferred by a tick rather than disabled in the submit handler. The
            answer travels as the pressed button's own name and value, and a
            button disabled while the form is still being gathered contributes
            neither — the request would arrive with no answer in it at all.
            Waiting a turn lets the submission leave first.

            Which one was pressed is remembered, because only that one should
            look like it is doing something. Flux turns a submit button's
            spinner on whenever it is disabled, so disabling both spun both —
            and a screen that appears to be allowing and refusing at the same
            time is not a reassuring one.
        --}}
        <form
            method="POST"
            action="{{ route('streetmesh.oauth.approve') }}"
            class="flex gap-3"
            x-data="{ answer: null }"
            x-on:submit="const pressed = $event.submitter?.value; setTimeout(() => (answer = pressed), 0)"
        >
            @csrf
            <input type="hidden" name="request_uri" value="{{ $permission->request_uri }}">

            {{--
                The one that was pressed is disabled, which is what draws its
                spinner. The other is only made inert — unpressable and faded,
                but not pretending to be busy, because it is not.
            --}}
            <flux:button
                type="submit"
                name="answer"
                value="yes"
                variant="primary"
                class="w-full transition-opacity"
                x-bind:disabled="answer === 'yes'"
                x-bind:inert="answer === 'no'"
                x-bind:class="answer === 'no' ? 'opacity-50' : ''"
            >
                {{ __('Allow') }}
            </flux:button>

            <flux:button
                type="submit"
                name="answer"
                value="no"
                class="w-full transition-opacity"
                x-bind:disabled="answer === 'no'"
                x-bind:inert="answer === 'yes'"
                x-bind:class="answer === 'yes' ? 'opacity-50' : ''"
            >
                {{ __('Not now') }}
            </flux:button>
        </form>
    </div>
</x-layouts::auth>
