@php
    $bg    = $store->background_color ?? '#1F2937';
    $brand = $store->brand_color ?? '#0EA5E9';
    $hex   = ltrim($bg, '#');
    $brandHex = ltrim($brand, '#');
    if (strlen($hex) === 6) {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $lum = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        $textOnBg  = $lum < 0.5 ? '#ffffff' : '#111827';
        $mutedOnBg = $lum < 0.5 ? 'rgba(255,255,255,0.85)' : 'rgba(17,24,39,0.75)';
    } else {
        $textOnBg  = '#ffffff';
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
    $joinCardLabel = $brandIsVeryLight ? 'rgba(248,250,252,0.88)' : '#374151';
    $joinInputBg = $brandIsVeryLight ? 'rgba(255,255,255,0.06)' : '#F8FAFC';
    $joinInputText = $brandIsVeryLight ? '#F8FAFC' : '#111827';
    $joinInputBorder = $brandIsVeryLight ? 'rgba(255,255,255,0.16)' : '#D1D5DB';
    $joinInputPlaceholder = $brandIsVeryLight ? 'rgba(248,250,252,0.45)' : '#9CA3AF';
    $textOnBrand = $brandLum < 0.58 ? '#ffffff' : '#111827';
    $secondaryBorder = $brandIsVeryLight ? 'rgba(255,255,255,0.22)' : $brand;
    $secondaryText = $brandIsVeryLight ? '#F8FAFC' : $brand;
    $secondaryHoverBg = $brandIsVeryLight ? 'rgba(255,255,255,0.08)' : $brand;
    $secondaryHoverText = $brandIsVeryLight ? '#FFFFFF' : $textOnBrand;
    $dividerColor = $brandIsVeryLight ? 'rgba(255,255,255,0.12)' : '#F1F5F9';
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
        <title>Find My Card – {{ $store->name }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            .join-page { background-color: {{ $bg }}; color: {{ $textOnBg }}; }
            .join-muted { color: {{ $mutedOnBg }}; }
            .join-card { background: {{ $joinCardBg }}; color: {{ $joinCardText }}; }
            .join-card-title { color: {{ $joinCardStrong }}; }
            .join-card-body { color: {{ $joinCardMuted }}; }
            .join-card-label { color: {{ $joinCardLabel }}; }
            .join-btn { background-color: {{ $brand }}; color: {{ $textOnBrand }}; }
            .join-btn:hover { filter: brightness(1.1); }
            .join-btn-outline { border: 2px solid {{ $secondaryBorder }}; color: {{ $secondaryText }}; background: transparent; }
            .join-btn-outline:hover { background-color: {{ $secondaryHoverBg }}; color: {{ $secondaryHoverText }}; }
            .join-input {
                background-color: {{ $joinInputBg }};
                color: {{ $joinInputText }};
                border-color: {{ $joinInputBorder }};
            }
            .join-input::placeholder { color: {{ $joinInputPlaceholder }}; }
            .join-input:focus { border-color: {{ $brand }}; box-shadow: 0 0 0 3px {{ $brand }}40; }
        </style>
    </head>
    <body class="font-sans antialiased join-page min-h-screen min-h-[100dvh] flex flex-col">
        <div class="flex flex-col flex-1 w-full max-w-md mx-auto px-4 py-6 sm:px-6 sm:py-8 lg:py-10">

            {{-- Back link --}}
            <div class="text-center mb-4 sm:mb-6">
                <a href="{{ route('join.index', ['slug' => $store->slug, 't' => $token]) }}" class="inline-flex items-center text-sm join-muted hover:opacity-90">
                    <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    Back
                </a>
            </div>

            <div class="join-card rounded-2xl shadow-xl p-6 sm:p-8 w-full">
                @if($store->logo_path)
                    <img src="{{ $store->logo_url }}" alt="{{ $store->name }}" class="h-12 w-auto mx-auto mb-4 sm:h-14 object-contain">
                @endif

                <h2 class="join-card-title text-xl sm:text-2xl font-bold text-center mb-1">Find my card</h2>
                <p class="join-card-body text-center text-sm sm:text-base mb-6">
                    Enter your email to access your loyalty card for <strong class="join-card-title">{{ $store->name }}</strong>.
                </p>

                <form action="{{ route('join.lookup', ['slug' => $store->slug, 't' => $token]) }}" method="POST" class="space-y-4 sm:space-y-5">
                    @csrf
                    <div>
                        <label for="email" class="join-card-label block mb-1.5 text-sm font-medium">Email address</label>
                        <input
                            id="email" name="email" type="email" autocomplete="email" required
                            value="{{ old('email') }}"
                            placeholder="you@example.com"
                            class="join-input w-full rounded-xl border text-sm sm:text-base px-4 py-3 focus:outline-none transition"
                        >
                        @error('email')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="pt-1">
                        <button type="submit" class="join-btn w-full font-semibold rounded-xl text-sm sm:text-base px-4 py-3.5 focus:outline-none focus:ring-2 focus:ring-offset-2 transition" style="--tw-ring-color: {{ $brand }};">
                            Open my card
                        </button>
                    </div>
                </form>

                <div class="mt-6 pt-5 text-center" style="border-top: 1px solid {{ $dividerColor }};">
                    <p class="join-card-body text-sm mb-3">Don't have a card yet?</p>
                    <a href="{{ route('join.show', ['slug' => $store->slug, 't' => $token]) }}"
                        class="join-btn-outline w-full inline-flex justify-center items-center py-3 px-4 rounded-xl text-sm sm:text-base font-semibold transition focus:outline-none focus:ring-2 focus:ring-offset-2"
                        style="--tw-ring-color: {{ $brand }};">
                        Create a new card
                    </a>
                </div>
            </div>
        </div>

        <script>
            function prefillEmail() {
                try {
                    var savedEmail = localStorage.getItem('kawhe_last_email_{{ $store->id }}');
                    var oldEmail = @json(old('email'));
                    if (savedEmail && !oldEmail) {
                        var emailInput = document.getElementById('email');
                        if (emailInput && !emailInput.value) {
                            emailInput.value = savedEmail;
                            emailInput.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                    }
                } catch (e) {}
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', prefillEmail);
            } else {
                prefillEmail();
            }
            setTimeout(prefillEmail, 100);
            window.addEventListener('load', prefillEmail);
        </script>
    </body>
</html>
