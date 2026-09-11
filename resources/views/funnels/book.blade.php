<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ __('Book an Appointment') }} — {{ $funnel->headline }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100 dark:bg-gray-900 flex items-center justify-center p-6">
            <div class="w-full max-w-xl bg-white dark:bg-gray-800 shadow-sm rounded-lg p-8">
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    {{ __('Book an Appointment') }}
                </h1>
                <p class="mt-2 text-gray-600 dark:text-gray-400">
                    {{ $funnel->headline }}
                </p>

                <form method="GET" action="{{ route('funnels.public.book', $funnel->slug) }}" class="mt-6">
                    <x-input-label for="date" :value="__('Choose a date')" />
                    <x-text-input id="date" class="block mt-1 w-full" type="date" name="date"
                            :value="$date?->format('Y-m-d')" required />
                    <x-input-error :messages="$errors->get('date')" class="mt-2" />

                    <x-primary-button class="mt-3">
                        {{ __('Show Available Times') }}
                    </x-primary-button>
                </form>

                @if ($date)
                    <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                        @if (empty($slots))
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ __('No available times on :date. Please choose another date.', ['date' => $date->format('M j, Y')]) }}
                            </p>
                        @else
                            <form method="POST" action="{{ route('funnels.public.book.confirm', $funnel->slug) }}" class="space-y-4">
                                @csrf
                                <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">

                                <div>
                                    <x-input-label :value="__('Choose a time')" />

                                    <div class="mt-2 space-y-2">
                                        @foreach ($slots as $slot)
                                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                                <input type="radio" name="start_time" value="{{ $slot['start']->format('H:i') }}"
                                                        @checked(old('start_time') === $slot['start']->format('H:i')) required>
                                                {{ $slot['start']->format('g:i A') }}
                                            </label>
                                        @endforeach
                                    </div>

                                    <x-input-error :messages="$errors->get('start_time')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="name" :value="__('Name')" />
                                    <x-text-input id="name" class="block mt-1 w-full" type="text" name="name"
                                            :value="old('name', $prefillContact->first_name ?? '')" required />
                                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="email" :value="__('Email')" />
                                    <x-text-input id="email" class="block mt-1 w-full" type="email" name="email"
                                            :value="old('email', $prefillContact->email ?? '')" required />
                                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="phone" :value="__('Phone')" />
                                    <x-text-input id="phone" class="block mt-1 w-full" type="text" name="phone"
                                            :value="old('phone', $prefillContact->phone ?? '')" required />
                                    <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                                </div>

                                <x-primary-button class="w-full justify-center">
                                    {{ __('Confirm Booking') }}
                                </x-primary-button>
                            </form>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </body>
</html>
