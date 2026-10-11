<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $funnel->headline }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100 dark:bg-gray-900 flex items-center justify-center p-6">
            <div class="w-full max-w-xl bg-white dark:bg-gray-800 shadow-sm rounded-lg p-8">
                @if (session('submitted'))
                    <div class="text-center py-8">
                        <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                            {{ __('Thank you!') }}
                        </h1>
                        <p class="mt-2 text-gray-600 dark:text-gray-400">
                            {{ __('We will send you the offer code shortly.') }}
                        </p>

                        @if ($funnel->calendar_id && $funnel->calendar)
                            <a href="{{ route('funnels.public.book', $funnel->slug) }}"
                                    class="mt-6 inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                                {{ __('Book Your Appointment') }}
                            </a>
                        @endif
                    </div>
                @else
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ $funnel->headline }}
                    </h1>

                    @if ($funnel->subheadline)
                        <p class="mt-2 text-gray-600 dark:text-gray-400">
                            {{ $funnel->subheadline }}
                        </p>
                    @endif

                    @include('funnels._lead-form')
                @endif
            </div>
        </div>
    </body>
</html>
