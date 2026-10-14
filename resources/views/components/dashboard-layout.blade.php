@props(['title' => 'Dashboard'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Kawhe') }} - {{ $title }}</title>
    <x-favicon />
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('scripts')
</head>
<body class="dashboard-page bg-stone-50 font-sans text-stone-900 antialiased">
    <a href="#dashboard-content" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:p-4">Skip to content</a>
    <main id="dashboard-content" class="mx-auto max-w-[1440px] space-y-6 p-6 lg:p-10">
        <a href="{{ route('dashboard') }}" aria-label="Kawhe home" class="inline-flex items-center gap-3 text-xl font-semibold text-brand-700">
            <img src="{{ asset('images/logo.svg') }}" width="28" height="37" alt="" class="h-[37px] w-[28px] object-contain">
            <span>kawhe</span>
        </a>
        <x-flash-messages />
        {{ $slot }}
    </main>
</body>
</html>
