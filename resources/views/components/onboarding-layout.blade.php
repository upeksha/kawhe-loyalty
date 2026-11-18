@props(['header' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Kawhe') }} – Setup</title>

        <x-favicon />
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('scripts')
    </head>
    <body {{ $attributes->class('kawhe-setup min-h-screen font-sans antialiased text-stone-900') }}>
        <header class="kawhe-setup-header">
            <a href="{{ route('merchant.onboarding.wizard.index') }}" aria-label="Kawhe setup" class="kawhe-setup-wordmark">
                <x-application-logo class="kawhe-setup-header-logo" />
                <span aria-hidden="true">kawhe</span>
            </a>
            <div class="flex items-center gap-4 text-sm text-stone-600">
                @if($header)<span class="hidden sm:inline">{{ $header }}</span>@endif
                @if(Route::has('merchant.support.index'))
                    <a href="{{ route('merchant.support.index') }}" class="font-semibold text-brand-800">Need help?</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="font-semibold text-brand-800">Log out</button>
                </form>
            </div>
        </header>

        <main class="kawhe-setup-main">
            <x-flash-messages />
            {{ $slot }}
        </main>
    </body>
</html>
