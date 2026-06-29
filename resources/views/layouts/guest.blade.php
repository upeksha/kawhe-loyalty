<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <meta name="theme-color" content="#2D5D47">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Kawhe') }}">
        <x-favicon />
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">

        <title>{{ config('app.name', 'Kawhe') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-stone-50 text-stone-900">

        <div class="min-h-screen flex flex-col items-center justify-center px-4 py-10 sm:py-16">

            {{-- Logo --}}
            <a href="/" class="mb-8 block">
                <x-application-logo class="h-24 w-auto mx-auto" />
            </a>

            {{-- Card --}}
            <div class="w-full max-w-md bg-white rounded-2xl shadow-lg shadow-stone-200/60 border border-stone-200/80 px-8 py-8">
                {{ $slot }}
            </div>

        </div>

    </body>
</html>
