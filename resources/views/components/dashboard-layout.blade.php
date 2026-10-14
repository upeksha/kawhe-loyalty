@props(['title' => 'Dashboard', 'workspace' => false])
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
    @if($workspace && auth()->check())
        <div class="workspace-shell" x-data="{ menuOpen: false }" @keydown.escape.window="if(menuOpen) { menuOpen = false; $refs.menuToggle.focus() }">
            <aside class="workspace-sidebar" :data-open="menuOpen">
                <div class="workspace-brand-row">
                    <a href="{{ route('dashboard') }}" aria-label="Kawhe home" class="workspace-brand"><img src="{{ asset('images/logo.svg') }}" width="28" height="37" alt="">kawhe</a>
                    <button type="button" class="workspace-menu-toggle" x-ref="menuToggle" @click="menuOpen = !menuOpen" :aria-expanded="menuOpen" aria-controls="workspace-navigation">Menu <span aria-hidden="true">☰</span></button>
                </div>
                <div id="workspace-navigation" class="workspace-navigation" :data-open="menuOpen">
                    <x-merchant-navigation />
                    <div class="workspace-account">
                        <span class="workspace-avatar" aria-hidden="true">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                        <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold">{{ auth()->user()->name }}</p><p class="text-xs text-stone-500">{{ auth()->user()->is_staff_account ? 'Staff account' : 'Business owner' }}</p></div>
                        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="workspace-signout" aria-label="Sign out" title="Sign out"><x-merchant-icon name="logout" /></button></form>
                    </div>
                </div>
            </aside>
            <div class="workspace-main">
                <div class="workspace-topbar">
                    <div class="flex items-center gap-3"><span class="text-sm text-stone-500">Merchant workspace</span>@if(app()->environment('testing', 'local'))<span class="workspace-environment">Testing</span>@endif</div>
                    <x-ui.button href="{{ route('merchant.scanner') }}" variant="secondary" size="sm" :aria-current="request()->routeIs('merchant.scanner') ? 'page' : null"><x-merchant-icon name="scan" />Open scanner</x-ui.button>
                </div>
                <main id="dashboard-content" class="workspace-content"><x-flash-messages />{{ $slot }}</main>
            </div>
        </div>
    @else
        <main id="dashboard-content" class="mx-auto max-w-[1440px] space-y-6 p-6 lg:p-10">
            <a href="{{ route('dashboard') }}" aria-label="Kawhe home" class="workspace-brand"><img src="{{ asset('images/logo.svg') }}" width="28" height="37" alt="">kawhe</a>
            <x-flash-messages />{{ $slot }}
        </main>
    @endif
</body>
</html>
