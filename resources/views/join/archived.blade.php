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
    } else {
        $brandLum = 0.5;
    }
    $brandIsVeryLight = $brandLum > 0.9;
    $cardBg = $brandIsVeryLight ? 'rgba(17,24,39,0.92)' : 'rgba(255,255,255,0.97)';
    $cardText = $brandIsVeryLight ? '#F8FAFC' : '#111827';
    $cardMuted = $brandIsVeryLight ? 'rgba(248,250,252,0.76)' : '#4B5563';
    $cardSoft = $brandIsVeryLight ? 'rgba(248,250,252,0.62)' : '#6B7280';
    $textOnBrand = $brandLum < 0.58 ? '#ffffff' : '#111827';
    $outlineBorder = $brandIsVeryLight ? 'rgba(255,255,255,0.22)' : $brand;
    $outlineText = $brandIsVeryLight ? '#F8FAFC' : $brand;
    $outlineHoverBg = $brandIsVeryLight ? 'rgba(255,255,255,0.08)' : $brand;
    $outlineHoverText = $brandIsVeryLight ? '#FFFFFF' : $textOnBrand;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="{{ $bg }}">
        <title>Store Temporarily Unavailable – {{ $store->name }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            .join-page { background-color: {{ $bg }}; color: {{ $textOnBg }}; }
            .join-muted { color: {{ $mutedOnBg }}; }
            .limit-card { background: {{ $cardBg }}; color: {{ $cardText }}; }
            .limit-body { color: {{ $cardMuted }}; }
            .limit-soft { color: {{ $cardSoft }}; }
            .limit-btn {
                border: 2px solid {{ $outlineBorder }};
                color: {{ $outlineText }};
                background: transparent;
            }
            .limit-btn:hover { background-color: {{ $outlineHoverBg }}; color: {{ $outlineHoverText }}; }
        </style>
    </head>
    <body class="font-sans antialiased join-page min-h-screen min-h-[100dvh] flex flex-col">
        <div class="flex flex-col flex-1 justify-center py-12 px-6">
            <div class="w-full max-w-md mx-auto">
                <div class="limit-card rounded-2xl shadow-xl px-6 py-8 sm:px-8">
                    <div class="text-center">
                        <div class="mb-4">
                            <svg class="mx-auto h-16 w-16 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                        </div>

                        <h2 class="text-2xl font-bold mb-2">Store temporarily unavailable</h2>

                        <p class="limit-body mb-4">
                            <strong class="text-inherit">{{ $store->name }}</strong> is currently archived, so new joins are paused for now.
                        </p>

                        <p class="limit-soft text-sm mb-6">
                            Existing customers can still talk to staff for help, but new card signups will stay paused until the store is restored.
                        </p>

                        <a href="{{ route('join.index', ['slug' => $store->slug, 't' => $token]) }}"
                           class="limit-btn w-full inline-flex justify-center py-3 px-4 rounded-xl text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-offset-2"
                           style="--tw-ring-color: {{ $brand }};">
                            Back to Store Link
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
