<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ __('Booking Requested') }} — {{ $funnel->headline }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100 dark:bg-gray-900 flex items-center justify-center p-6">
            <div class="w-full max-w-xl bg-white dark:bg-gray-800 shadow-sm rounded-lg p-8 text-center">
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    {{ __('Your appointment has been requested!') }}
                </h1>
                <p class="mt-2 text-gray-600 dark:text-gray-400">
                    {{ __("We've sent your request and will confirm shortly.") }}
                </p>
            </div>
        </div>
    </body>
</html>
