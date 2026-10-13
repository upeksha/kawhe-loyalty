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
        <meta name="referrer" content="no-referrer">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="{{ $theme->bg }}">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        @if($store ?? $account?->store)
            <meta name="apple-mobile-web-app-title" content="{{ ($store ?? $account->store)->name }}">
        @endif
        <link rel="manifest" href="{{ $resolvedManifestHref }}">
        <x-favicon />
        <title>{{ $resolvedDocumentTitle }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>{!! $theme->cssVariableBlock('.customer-page') !!}</style>
        @stack('head')
    </head>
    <body {{ $attributes->merge(['class' => 'overflow-x-hidden font-sans antialiased customer-page min-h-screen min-h-[100dvh] flex flex-col']) }}>
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

        @php
            $helpProgram = $program ?? $account?->loyaltyProgram;
        @endphp
        <footer class="customer-muted px-4 pb-6 text-center text-sm">
            <nav aria-label="Customer help" class="flex flex-wrap justify-center gap-x-6 gap-y-3">
                <a href="https://kawhe.shop/privacy-policy" rel="noreferrer" class="underline">Privacy</a>
                <a href="mailto:support@kawhe.shop?subject=Kawhe%20customer%20card%20support" class="underline">Support</a>
                @if($helpProgram && $helpProgram->join_token)
                    <a href="{{ route('join.existing', ['slug' => $helpProgram->slug, 't' => $helpProgram->join_token]) }}" class="underline">Recover my card</a>
                @endif
            </nav>
        </footer>

        @stack('overlays')
        @stack('scripts')
    </body>
</html>
