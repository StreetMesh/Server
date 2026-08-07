<x-layouts::auth :title="__('Register')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Create an account')" :description="__('Enter your details below to create your account')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf
            <!-- Name -->
            <flux:input
                name="name"
                :label="__('Name')"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                :placeholder="__('Full name')"
            />

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                placeholder="email@example.com"
            />

            {{--
                The address, which is the part of this form that is not like
                the rest of it. Everything else here is between this person and
                this server; this is the name every other server on the network
                will know them by.

                The host is shown and not editable, because it is not theirs to
                choose — a resident picks the name in front of it, and this
                server supplies its own after it.

                It no longer says the name is permanent, because it is not. A
                resident's identifier is a did:plc, which is the hash of the
                operation that created it rather than an address, so the name
                above it can change without costing them a single record they
                have already signed. What is worth saying is that people will
                know them by it.
            --}}
            {{--
                Shaped as it is typed rather than refused afterwards.

                A rejected address costs a round trip and a red sentence about a
                name somebody had already settled on. The rule is the one
                `Handle` enforces, which is the hostname rule: letters, numbers
                and hyphens, not starting or ending with a hyphen.

                Lower-cased here because `Handle` lower-cases it anyway, so a
                capital would be accepted and then quietly changed — better to
                show what is actually being claimed.

                A trailing hyphen is left alone until the field is left. Taking
                it away as it is typed would mean nobody could ever type
                `mary-jane`: the hyphen would vanish before the `j` arrived.
                A leading one has no such excuse and goes immediately.

                Only rewritten when the value would differ, because assigning to
                `value` puts the caret at the end, and doing that on every
                keystroke makes the field impossible to edit in the middle.
            --}}
            <flux:input
                name="address"
                :label="__('Address')"
                :value="old('address')"
                type="text"
                required
                autocomplete="username"
                autocapitalize="off"
                spellcheck="false"
                maxlength="63"
                :placeholder="__('username')"
                :description="__('Choose well: this is how people will know you.')"
                x-data="{
                    shape(settled) {
                        let name = $el.value.toLowerCase().replace(/[^a-z0-9-]/g, '').replace(/^-+/, '');

                        if (settled) {
                            name = name.replace(/-+$/, '');
                        }

                        if (name === $el.value) {
                            return;
                        }

                        const at = Math.max(0, $el.selectionStart - ($el.value.length - name.length));

                        $el.value = name;
                        $el.setSelectionRange(at, at);
                    },
                }"
                x-on:input="shape(false)"
                x-on:blur="shape(true)"
            >
                <x-slot name="iconTrailing">
                    <flux:text class="pr-3 whitespace-nowrap">.{{ $residentHost }}</flux:text>
                </x-slot>
            </flux:input>

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <!-- Confirm Password -->
            <flux:input
                name="password_confirmation"
                :label="__('Confirm password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Confirm password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                    {{ __('Create account') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Already have an account?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
