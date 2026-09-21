<x-guest-layout>
    <div class="mb-8 text-center">
        <h1 class="text-xl font-semibold text-text-primary">{{ __('Create your account') }}</h1>
        <p class="mt-1 text-sm text-text-secondary">{{ __('Set up your business in a couple of minutes') }}</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        <!-- Business Name -->
        <div>
            <x-input-label for="business_name" :value="__('Business Name')" />
            <x-text-input id="business_name" class="block mt-1 w-full" type="text" name="business_name" :value="old('business_name')" required autofocus autocomplete="organization" />
            <x-input-error :messages="$errors->get('business_name')" class="mt-2" />
        </div>

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div>
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between pt-2">
            <a class="text-sm text-brand hover:underline focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand rounded-md" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button>
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
