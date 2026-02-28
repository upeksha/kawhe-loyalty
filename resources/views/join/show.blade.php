@php
    $bg = $store->background_color ?? '#1F2937';
    $brand = $store->brand_color ?? '#0EA5E9';
    $hex = ltrim($bg, '#');
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
            .join-card { background: rgba(255,255,255,0.97); color: #111827; }
            .join-btn { background-color: {{ $brand }}; }
            .join-btn:hover { filter: brightness(1.1); }
            .join-input:focus { border-color: {{ $brand }}; box-shadow: 0 0 0 3px {{ $brand }}40; }
        </style>
    </head>
    <body class="font-sans antialiased join-page min-h-screen min-h-[100dvh] flex flex-col">
        <div class="flex flex-col flex-1 w-full max-w-md mx-auto px-4 py-6 sm:px-6 sm:py-8 lg:py-10">
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
                <h2 class="text-xl sm:text-2xl font-bold text-center text-stone-900 mb-2">Join {{ $store->name }}</h2>
                <p class="text-stone-600 text-center text-sm sm:text-base mb-6">
                    Collect stamps and earn {{ $store->reward_title }}!
                </p>

                @php
                    $formConfig = $store->registration_form_config;
                @endphp
                <form method="POST" action="{{ route('join.store', ['slug' => $store->slug, 't' => $token]) }}" class="space-y-4 sm:space-y-5">
                    @csrf

                    <div>
                        <label for="email" class="block mb-1.5 text-sm font-medium text-stone-700">Email Address</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" class="join-input w-full rounded-xl border border-stone-300 bg-stone-50 text-stone-900 text-sm sm:text-base px-4 py-3 focus:outline-none transition" placeholder="you@example.com" required>
                        <x-input-error :messages="$errors->get('email')" class="mt-1.5 text-sm" />
                    </div>

                    @if(!empty($formConfig['first_name']['enabled']))
                    <div>
                        <label for="first_name" class="block mb-1.5 text-sm font-medium text-stone-700">First name{{ !empty($formConfig['first_name']['required']) ? '' : ' (optional)' }}</label>
                        <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" class="join-input w-full rounded-xl border border-stone-300 bg-stone-50 text-stone-900 text-sm sm:text-base px-4 py-3 focus:outline-none transition" placeholder="First name" {{ !empty($formConfig['first_name']['required']) ? 'required' : '' }}>
                        <x-input-error :messages="$errors->get('first_name')" class="mt-1.5 text-sm" />
                    </div>
                    @endif

                    @if(!empty($formConfig['last_name']['enabled']))
                    <div>
                        <label for="last_name" class="block mb-1.5 text-sm font-medium text-stone-700">Last name{{ !empty($formConfig['last_name']['required']) ? '' : ' (optional)' }}</label>
                        <input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}" class="join-input w-full rounded-xl border border-stone-300 bg-stone-50 text-stone-900 text-sm sm:text-base px-4 py-3 focus:outline-none transition" placeholder="Last name" {{ !empty($formConfig['last_name']['required']) ? 'required' : '' }}>
                        <x-input-error :messages="$errors->get('last_name')" class="mt-1.5 text-sm" />
                    </div>
                    @endif

                    @if(!empty($formConfig['phone']['enabled']))
                    <div>
                        <label for="phone" class="block mb-1.5 text-sm font-medium text-stone-700">Phone{{ !empty($formConfig['phone']['required']) ? '' : ' (optional)' }}</label>
                        <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" class="join-input w-full rounded-xl border border-stone-300 bg-stone-50 text-stone-900 text-sm sm:text-base px-4 py-3 focus:outline-none transition" placeholder="Phone number" {{ !empty($formConfig['phone']['required']) ? 'required' : '' }}>
                        <x-input-error :messages="$errors->get('phone')" class="mt-1.5 text-sm" />
                    </div>
                    @endif

                    @if(!empty($formConfig['birthday']['enabled']))
                    <div>
                        <label for="birthday" class="block mb-1.5 text-sm font-medium text-stone-700">Birthday{{ !empty($formConfig['birthday']['required']) ? '' : ' (optional)' }}</label>
                        <input type="date" id="birthday" name="birthday" value="{{ old('birthday') }}" class="join-input w-full rounded-xl border border-stone-300 bg-stone-50 text-stone-900 text-sm sm:text-base px-4 py-3 focus:outline-none transition" {{ !empty($formConfig['birthday']['required']) ? 'required' : '' }}>
                        <x-input-error :messages="$errors->get('birthday')" class="mt-1.5 text-sm" />
                    </div>
                    @endif

                    <div class="pt-1">
                        <button type="submit" class="join-btn w-full text-white font-semibold rounded-xl text-sm sm:text-base px-4 py-3.5 focus:outline-none focus:ring-2 focus:ring-offset-2 transition" style="--tw-ring-color: {{ $brand }};">
                            Join Now
                        </button>
                    </div>
                </form>
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
