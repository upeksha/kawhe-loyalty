@php
    $pageTitle = $title !== '' ? $title : $displayTitle;
    $resolvedDocumentTitle = $documentTitle ?? (
        $title !== '' && $title !== $displayTitle
            ? "{$pageTitle} – {$displayTitle}"
            : "{$displayTitle} – ".config('app.name', 'Kawhe')
    );
    $resolvedManifestHref = $manifestHref ?? asset('manifest.webmanifest');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="{{ $theme->bg }}">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        @if($store ?? $account?->store)
            <meta name="apple-mobile-web-app-title" content="{{ ($store ?? $account->store)->name }}">
        @endif
        <link rel="manifest" href="{{ $resolvedManifestHref }}">
        <link rel="apple-touch-icon" href="{{ asset('favicon.ico') }}">
        <title>{{ $resolvedDocumentTitle }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>{!! $theme->cssVariableBlock('.customer-page') !!}</style>
        @stack('head')
    </head>
    <body {{ $attributes->merge(['class' => 'font-sans antialiased customer-page min-h-screen min-h-[100dvh] flex flex-col']) }}>
        <div @class([
            'customer-shell flex-1',
            'customer-shell--centered py-8 sm:py-10 lg:py-12' => $centered,
            'customer-shell--card py-6 sm:py-8' => $shell === 'card',
            'py-6 sm:py-8 lg:py-10' => ! $centered && $shell !== 'card',
        ])>
            @isset($back)
                <div class="text-center mb-4 sm:mb-6">
                    {{ $back }}
                </div>
            @endisset

            @isset($hero)
                {{ $hero }}
            @endisset

            {{ $slot }}
        </div>

        @stack('overlays')
        @stack('scripts')
    </body>
</html>
