@php
    $bg = $store->background_color ?? '#1F2937';
    $brand = $store->brand_color ?? '#0EA5E9';
    $hex = ltrim($bg, '#');
    $brandHex = ltrim($brand, '#');
    if (strlen($hex) === 6) {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $lum = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        $textOnBg = $lum < 0.5 ? '#ffffff' : '#111827';
        $mutedOnBg = $lum < 0.5 ? 'rgba(255,255,255,0.85)' : 'rgba(17,24,39,0.75)';
    } else {
        $textOnBg = '#ffffff';
        $mutedOnBg = 'rgba(255,255,255,0.85)';
    }
    if (strlen($brandHex) === 6) {
        $brandR = hexdec(substr($brandHex, 0, 2));
        $brandG = hexdec(substr($brandHex, 2, 2));
        $brandB = hexdec(substr($brandHex, 4, 2));
        $brandLum = (0.299 * $brandR + 0.587 * $brandG + 0.114 * $brandB) / 255;
        $brandLight = sprintf('#%02X%02X%02X',
            min(255, (int) round($brandR + ((255 - $brandR) * 0.18))),
            min(255, (int) round($brandG + ((255 - $brandG) * 0.18))),
            min(255, (int) round($brandB + ((255 - $brandB) * 0.18)))
        );
        $brandDark = sprintf('#%02X%02X%02X',
            max(0, (int) round($brandR * 0.72)),
            max(0, (int) round($brandG * 0.72)),
            max(0, (int) round($brandB * 0.72))
        );
    } else {
        $brandLum = 0.5;
        $brandLight = '#3DB7ED';
        $brandDark = '#0A769F';
    }
    $brandIsVeryLight = $brandLum > 0.9;
    $joinCardBg = 'linear-gradient(145deg, ' . $brandLight . ', ' . $brandDark . ')';
    $joinCardText = $brandIsVeryLight ? '#F8FAFC' : '#111827';
    $joinCardMuted = $brandIsVeryLight ? 'rgba(248,250,252,0.76)' : '#4B5563';
    $joinCardStrong = $brandIsVeryLight ? '#FFFFFF' : '#111827';
    $joinCardBorder = $brandIsVeryLight ? 'rgba(255,255,255,0.14)' : 'rgba(17,24,39,0.08)';
    $textOnBrand = $brandLum < 0.58 ? '#ffffff' : '#111827';
    $secondaryBorder = $brandIsVeryLight ? 'rgba(255,255,255,0.2)' : $brand;
    $secondaryText = $brandIsVeryLight ? '#F8FAFC' : $brand;
    $secondaryHoverBg = $brandIsVeryLight ? 'rgba(255,255,255,0.08)' : $brand;
    $secondaryHoverText = $brandIsVeryLight ? '#FFFFFF' : $textOnBrand;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="{{ $bg }}">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="{{ $store->name }}">
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <link rel="apple-touch-icon" href="{{ asset('favicon.ico') }}">
        <title>Join {{ $store->name }} – {{ config('app.name', 'Kawhe') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            .join-page { background-color: {{ $bg }}; color: {{ $textOnBg }}; }
            .join-muted { color: {{ $mutedOnBg }}; }
            .join-card { background: {{ $joinCardBg }}; color: {{ $joinCardText }}; }
            .join-card-title { color: {{ $joinCardStrong }}; }
            .join-card-body { color: {{ $joinCardMuted }}; }
            .join-btn-primary { background-color: {{ $brand }}; color: {{ $textOnBrand }}; }
            .join-btn-primary:hover { filter: brightness(1.1); }
            .join-btn-secondary { border-color: {{ $secondaryBorder }}; color: {{ $secondaryText }}; }
            .join-btn-secondary:hover { background: {{ $secondaryHoverBg }}; color: {{ $secondaryHoverText }}; }
        </style>
    </head>
    <body class="font-sans antialiased join-page min-h-screen min-h-[100dvh] flex flex-col" x-data="joinLanding()">
        <div class="flex flex-col flex-1 justify-center w-full max-w-md mx-auto px-4 py-8 sm:px-6 sm:py-10 lg:py-12">
            <div class="text-center mb-6 sm:mb-8">
                @if($store->logo_path)
                    <img src="{{ $store->logo_url }}" alt="{{ $store->name }}" class="h-16 w-auto mx-auto object-contain sm:h-20" style="max-height: 5rem;">
                @else
                    <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold tracking-tight">{{ $store->name }}</h1>
                @endif
                <p class="mt-2 sm:mt-3 text-sm sm:text-base join-muted">
                    Join our loyalty program and start earning {{ strtolower($store->reward_title ?: 'rewards') }}.
                </p>
                <p class="mt-1 text-xs sm:text-sm join-muted">
                    Most customers finish signup in under a minute.
                </p>
            </div>

            <div class="join-card rounded-2xl shadow-xl p-6 sm:p-8 w-full">
                <div class="space-y-4 sm:space-y-5">
                    <template x-if="lastToken">
                        <div>
                            <a :href="'/c/' + lastToken" class="join-btn-primary w-full flex justify-center items-center py-3.5 px-4 rounded-xl text-sm sm:text-base font-semibold transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-white" style="--tw-ring-color: {{ $brand }};">
                                Open My Card
                            </a>
                            <p class="join-card-body mt-2 text-center text-xs sm:text-sm">
                                Found a card saved on this device.
                            </p>
                        </div>
                    </template>

                    <div>
                        <a href="{{ route('join.existing', ['slug' => $store->slug, 't' => $token]) }}" class="join-btn-secondary w-full flex justify-center items-center py-3.5 px-4 rounded-xl border-2 text-sm sm:text-base font-semibold bg-transparent transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-white" style="--tw-ring-color: {{ $brand }};">
                            I already have a card
                        </a>
                    </div>

                    <div>
                        <a href="{{ route('join.show', ['slug' => $store->slug, 't' => $token]) }}" class="join-btn-primary w-full flex justify-center items-center py-3.5 px-4 rounded-xl text-sm sm:text-base font-semibold transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-white" style="--tw-ring-color: {{ $brand }};">
                            Create a new card
                        </a>
                        <p class="join-card-body mt-2 text-center text-xs sm:text-sm">
                            Save your card to Apple Wallet or Google Wallet after you join.
                        </p>
                        <p class="join-card-body mt-1 text-center text-xs">
                            Use the same email again later if you ever need to recover your card.
                        </p>
                    </div>

                    <template x-if="lastToken">
                        <div class="text-center pt-1">
                            <button type="button" @click="clearLastCard()" class="text-xs sm:text-sm text-stone-400 hover:text-stone-600 underline">
                                Use a different card/email
                            </button>
                        </div>
                    </template>
                </div>
            </div>

            <p class="mt-6 text-center join-muted text-xs sm:text-sm">
                <a href="/" class="underline hover:no-underline">Back to home</a>
            </p>
        </div>

        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('joinLanding', () => ({
                    lastToken: localStorage.getItem('kawhe_last_card_{{ $store->id }}'),
                    clearLastCard() {
                        localStorage.removeItem('kawhe_last_card_{{ $store->id }}');
                        this.lastToken = null;
                    }
                }));
            });
        </script>
    </body>
</html>
