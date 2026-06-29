<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <title>{{ $title ?? 'Card unavailable' }} – {{ config('app.name', 'Kawhe') }}</title>
        <x-favicon />
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-stone-950 text-white font-sans antialiased">
        <div class="mx-auto flex min-h-screen w-full max-w-lg items-center px-6 py-12">
            <div class="w-full rounded-3xl border border-white/10 bg-white/5 p-8 shadow-2xl backdrop-blur">
                <div class="mb-5 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-400/15 text-amber-300">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-7.938 4h15.876c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L2.33 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h1 class="text-2xl font-bold tracking-tight">{{ $title ?? 'Card unavailable' }}</h1>
                <p class="mt-3 text-sm leading-relaxed text-white/70">
                    {{ $message ?? 'This loyalty card is not available right now.' }}
                </p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="/" class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2 text-sm font-semibold text-stone-900 transition hover:bg-stone-200">
                        Back to home
                    </a>
                </div>
            </div>
        </div>
    </body>
</html>
