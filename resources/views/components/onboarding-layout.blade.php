@props(['header' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Kawhe') }} – Setup</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('scripts')
    </head>
    <body class="font-sans antialiased bg-stone-50 min-h-screen">
        <header class="sticky top-0 z-40 border-b border-stone-200/80 bg-white/90 backdrop-blur">
            <div class="max-w-5xl mx-auto flex items-center justify-between gap-4 h-16 px-4 sm:px-6">
                <a href="{{ route('merchant.onboarding.wizard.index') }}" class="flex items-center gap-2 shrink-0" aria-label="Continue setup">
                    <x-application-logo class="block h-8 w-auto fill-current text-brand-600" />
                </a>
                <p class="hidden sm:block text-sm text-stone-500 truncate">Setting up your loyalty card</p>
                <div class="flex items-center gap-3 shrink-0">
                    @if (Route::has('merchant.support.index'))
                        <a href="{{ route('merchant.support.index') }}" class="text-sm font-medium text-stone-600 hover:text-stone-900 transition-colors">
                            Need help?
                        </a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-sm font-medium text-stone-500 hover:text-stone-800 transition-colors">
                            Log out
                        </button>
                    </form>
                </div>
            </div>
            @if ($header)
                <div class="border-t border-stone-100 bg-stone-50/80">
                    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-3">
                        <h1 class="text-lg font-semibold text-stone-900">{{ $header }}</h1>
                    </div>
                </div>
            @endif
        </header>

        <main class="overflow-visible">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 py-6">
                <x-flash-messages />
                {{ $slot }}
            </div>
        </main>
    </body>
</html>
