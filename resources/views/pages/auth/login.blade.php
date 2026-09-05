<x-layouts::auth :title="__('Log in')">
    <div class="space-y-6">
        <flux:heading class="text-center" size="xl" level="1">{{ __('Log in to your account') }}</flux:heading>

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        @if ($teamInvitation)
            <x-team-invitation-alert :invitation="$teamInvitation" :action="__('Log in')" />
        @endif

        <x-passkey-verify />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <flux:field>
                <div class="mb-3 flex justify-between">
                    <flux:label>{{ __('Password') }}</flux:label>

                    @if (Route::has('password.request'))
                        <flux:link variant="subtle" class="text-sm" :href="route('password.request')" wire:navigate>
                            {{ __('Forgot your password?') }}
                        </flux:link>
                    @endif
                </div>

                <flux:input
                    name="password"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                    viewable
                />

                <flux:error name="password" />
            </flux:field>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

            <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                {{ __('Log in') }}
            </flux:button>
        </form>

        <flux:text class="text-center">
            {{ __('Don\'t have an account?') }}
            <flux:link
                :href="$teamInvitation ? route('register', ['invitation' => $teamInvitation['code']]) : route('register')"
                data-test="register-link"
                wire:navigate
            >
                {{ __('Sign up') }}
            </flux:link>
        </flux:text>
    </div>
</x-layouts::auth>
