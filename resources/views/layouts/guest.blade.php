<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Tech CRM') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-text-primary antialiased">
        <div class="min-h-screen flex flex-col items-center justify-center bg-bg-base px-4 py-12">
            <a href="/" class="mb-8 text-2xl font-bold tracking-tight text-text-primary">
                Tech<span class="text-brand">CRM</span>
            </a>

            <div class="w-full sm:max-w-md bg-bg-surface px-8 py-10 shadow-sm rounded-xl">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
