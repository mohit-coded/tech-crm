<!DOCTYPE html>
{{-- Rendered inside the iframe public/embed.js creates on a client's site:
     just the card contents, no page chrome, filling the frame edge to edge. --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $funnel->headline }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-bg-surface text-text-primary">
        <div class="p-6">
            @if ($submitted)
                <div class="text-center py-8">
                    <h1 class="text-2xl font-semibold text-text-primary">
                        {{ __('Thank you!') }}
                    </h1>
                    <p class="mt-2 text-text-secondary">
                        {{ __('We will send you the offer code shortly.') }}
                    </p>

                    {{-- Opens in a new tab rather than inside this iframe: the
                         booking pages keep FrameGuard, so they can't be
                         framed on the client's site. --}}
                    @if ($funnel->calendar_id && $funnel->calendar)
                        <a href="{{ route('funnels.public.book', $funnel->slug) }}" target="_blank" rel="noopener"
                                class="mt-6 inline-flex items-center px-4 py-2 bg-brand border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand/90 focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2 transition ease-in-out duration-150">
                            {{ __('Book Your Appointment') }}
                        </a>
                    @endif
                </div>
            @else
                <h1 class="text-2xl font-semibold text-text-primary">
                    {{ $funnel->headline }}
                </h1>

                @if ($funnel->subheadline)
                    <p class="mt-2 text-text-secondary">
                        {{ $funnel->subheadline }}
                    </p>
                @endif

                @include('funnels._lead-form', ['embed' => true])
            @endif
        </div>
    </body>
</html>
