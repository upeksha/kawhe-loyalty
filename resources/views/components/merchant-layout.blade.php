@props(['header' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }} - Merchant</title>

        <x-favicon />
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('scripts')
        <style>
            @media (min-width: 1024px) {
                .merchant-main-offset {
                    margin-left: 256px;
                    width: calc(100% - 256px);
                }
            }
        </style>
    </head>
    <body class="overflow-x-hidden font-sans antialiased bg-stone-50">
        @php
            $storesNavActive = request()->routeIs('merchant.stores.*');
            $cardsNavActive = request()->routeIs('merchant.programs.index') || request()->routeIs('merchant.stores.programs.*');
            $storesNavOpenDefault = $cardsNavActive;
        @endphp
        <div
            x-data="{
                sidebarOpen: false,
                sidebarOpener: null,
                openSidebar() {
                    this.sidebarOpener = document.activeElement;
                    this.sidebarOpen = true;
                    this.$nextTick(() => this.$refs.sidebarClose?.focus());
                },
                closeSidebar() {
                    this.sidebarOpen = false;
                    this.$nextTick(() => this.sidebarOpener?.focus());
                }
            }"
            @keydown.escape.window="if (sidebarOpen) closeSidebar()"
            class="min-h-screen"
        >
            <!-- Sidebar -->
            <aside 
                id="merchant-sidebar"
                x-cloak
                :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
                class="fixed inset-y-0 left-0 z-50 h-screen w-64 bg-white border-r border-stone-200 transform transition-transform duration-200 ease-in-out overflow-visible lg:translate-x-0"
            >
                <div class="flex h-full min-h-0 flex-col overflow-visible">
                    <!-- Logo -->
                    <div class="flex items-center justify-between h-16 px-6 border-b border-stone-200">
                        <a href="{{ route('merchant.dashboard') }}" class="flex items-center">
                            <x-application-logo class="block h-8 w-auto fill-current text-brand-600" />
                        </a>
                        <button 
                            x-ref="sidebarClose"
                            @click="closeSidebar()"
                            class="flex h-11 w-11 items-center justify-center rounded-lg text-stone-500 hover:bg-stone-100 hover:text-stone-700 lg:hidden"
                            aria-label="Close navigation"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Navigation -->
                    <nav class="flex-1 min-h-0 overflow-y-auto px-4 py-6 space-y-1">
                        <a 
                            href="{{ route('merchant.dashboard') }}" 
                            class="flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('merchant.dashboard') ? 'bg-brand-50 text-brand-700' : 'text-stone-700 hover:bg-stone-100' }}"
                        >
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            Dashboard
                        </a>
                        
                        <div x-data="{ storesNavOpen: @json($storesNavOpenDefault) }">
                            <div class="flex items-center gap-1">
                                <a
                                    href="{{ route('merchant.stores.index') }}"
                                    class="flex min-w-0 flex-1 items-center rounded-lg px-3 py-2 text-sm font-medium transition-colors {{ $storesNavActive ? 'bg-brand-50 text-brand-700' : 'text-stone-700 hover:bg-stone-100' }}"
                                >
                                    <svg class="mr-3 h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                    <span class="truncate">Stores</span>
                                </a>
                                @if(Auth::user()?->stores()->whereNull('deleted_at')->exists())
                                    <button
                                        type="button"
                                        @click="storesNavOpen = !storesNavOpen"
                                        class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg text-stone-500 transition-colors hover:bg-stone-100 hover:text-stone-700"
                                        :aria-expanded="storesNavOpen"
                                        aria-controls="merchant-nav-cards"
                                    >
                                        <svg class="h-4 w-4 transition-transform duration-200" :class="{ 'rotate-180': storesNavOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                        <span class="sr-only">Toggle cards menu</span>
                                    </button>
                                @endif
                            </div>
                            @if(Auth::user()?->stores()->whereNull('deleted_at')->exists())
                                <div
                                    id="merchant-nav-cards"
                                    x-show="storesNavOpen"
                                    x-transition:enter="transition ease-out duration-150"
                                    x-transition:enter-start="opacity-0 -translate-y-1"
                                    x-transition:enter-end="opacity-100 translate-y-0"
                                    x-transition:leave="transition ease-in duration-100"
                                    x-transition:leave-start="opacity-100 translate-y-0"
                                    x-transition:leave-end="opacity-0 -translate-y-1"
                                    class="mt-1 space-y-1"
                                    style="display: none;"
                                >
                                    <a
                                        href="{{ route('merchant.programs.index') }}"
                                        class="ml-11 flex items-center rounded-lg px-3 py-2 text-sm font-medium transition-colors {{ $cardsNavActive ? 'bg-brand-50 text-brand-700' : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900' }}"
                                    >
                                        <svg class="mr-3 h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M4 12h16M4 17h10M6 5h12a2 2 0 012 2v10a2 2 0 01-2 2H6a2 2 0 01-2-2V7a2 2 0 012-2z" />
                                        </svg>
                                        Cards
                                    </a>
                                </div>
                            @endif
                        </div>
                        
                        <a 
                            href="{{ route('merchant.customers.index') }}" 
                            class="flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('merchant.customers.*') ? 'bg-brand-50 text-brand-700' : 'text-stone-700 hover:bg-stone-100' }}"
                        >
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            Customers
                        </a>

                        <a
                            href="{{ route('merchant.support.index') }}"
                            class="flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('merchant.support.*') ? 'bg-brand-50 text-brand-700' : 'text-stone-700 hover:bg-stone-100' }}"
                        >
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16h6M12 3C7.03 3 3 6.582 3 11c0 2.014.836 3.854 2.216 5.263L4 21l4.237-1.12A10.47 10.47 0 0012 20c4.97 0 9-3.582 9-8s-4.03-9-9-9z" />
                            </svg>
                            Activity
                        </a>
                        
                        <a 
                            href="{{ route('merchant.scanner') }}" 
                            class="flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('merchant.scanner') ? 'bg-brand-50 text-brand-700' : 'text-stone-700 hover:bg-stone-100' }}"
                        >
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                            </svg>
                            Scanner
                        </a>
                        
                        @if(Route::has('billing.index'))
                        <a 
                            href="{{ route('billing.index') }}" 
                            class="flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('billing.*') ? 'bg-brand-50 text-brand-700' : 'text-stone-700 hover:bg-stone-100' }}"
                        >
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                            </svg>
                            Billing
                        </a>
                        @endif
                    </nav>

                    <!-- User Menu -->
                    <div class="relative z-[70] border-t border-stone-200 px-4 py-4" x-data="{ profileMenuOpen: false }" @keydown.escape.window="profileMenuOpen = false">
                        <div
                            x-show="profileMenuOpen"
                            x-transition.opacity
                            class="fixed inset-0 z-[60] lg:hidden"
                            @click="profileMenuOpen = false"
                            style="display: none;"
                        ></div>

                        <button
                            type="button"
                            @click="profileMenuOpen = !profileMenuOpen"
                            class="flex w-full items-center rounded-lg px-3 py-2 text-sm font-medium text-stone-700 transition-colors hover:bg-stone-100"
                        >
                            <div class="flex min-w-0 flex-1 items-center">
                                <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-brand-100">
                                    <span class="text-sm font-semibold text-brand-700">{{ substr(Auth::user()->name, 0, 1) }}</span>
                                </div>
                                <div class="ml-3 min-w-0 flex-1 overflow-hidden text-left">
                                    <div class="truncate text-sm font-medium text-stone-900">{{ Auth::user()->name }}</div>
                                    <div class="truncate text-xs text-stone-500">{{ Auth::user()->email }}</div>
                                </div>
                            </div>
                            <svg class="ml-2 h-4 w-4 flex-shrink-0 text-stone-500 transition-transform duration-200" :class="{ 'rotate-180': profileMenuOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div
                            x-show="profileMenuOpen"
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 translate-y-2"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 translate-y-0"
                            x-transition:leave-end="opacity-0 translate-y-2"
                            @click.outside="profileMenuOpen = false"
                            class="absolute inset-x-4 bottom-full mb-2 z-[80] overflow-visible rounded-2xl border border-stone-200 bg-white p-2 shadow-xl shadow-stone-900/10"
                            style="display: none;"
                        >
                            <a
                                href="{{ route('profile.edit') }}"
                                class="flex w-full items-center rounded-xl px-3 py-2 text-sm font-medium text-stone-700 transition hover:bg-stone-100"
                            >
                                {{ __('Profile') }}
                            </a>

                            <form method="POST" action="{{ route('logout') }}" class="mt-1">
                                @csrf
                                <button
                                    type="submit"
                                    class="flex w-full items-center rounded-xl px-3 py-2 text-sm font-medium text-stone-700 transition hover:bg-stone-100"
                                >
                                    {{ __('Log Out') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Overlay for mobile -->
            <div 
                x-show="sidebarOpen"
                @click="closeSidebar()"
                x-transition:enter="transition-opacity ease-linear duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity ease-linear duration-300"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-stone-900 bg-opacity-50 z-40 lg:hidden"
                style="display: none;"
            ></div>

            <!-- Main Content -->
            <div class="merchant-main-offset min-h-screen min-w-0">
                <!-- Top Header -->
                <header class="bg-white border-b border-stone-200">
                    <div class="flex items-center justify-between h-16 px-4 sm:px-6 lg:px-8">
                        <div class="flex items-center">
                            <button 
                                @click="openSidebar()"
                                class="mr-4 flex h-11 w-11 items-center justify-center rounded-lg text-stone-500 hover:bg-stone-100 hover:text-stone-700 lg:hidden"
                                aria-label="Open navigation"
                                aria-controls="merchant-sidebar"
                                :aria-expanded="sidebarOpen"
                            >
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                </svg>
                            </button>
                            @if($header)
                                <h1 class="text-xl font-semibold text-stone-900">
                                    {{ $header }}
                                </h1>
                            @endif
                        </div>
                    </div>
                </header>

                <!-- Page Content -->
                <main class="overflow-visible">
                    <div class="py-6 px-4 sm:px-6 lg:px-8">
                        <x-flash-messages />
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>
    </body>
</html>
