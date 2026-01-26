<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }} - Merchant</title>

        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('scripts')
    </head>
    <body class="font-sans antialiased bg-stone-50">
        <div x-data="{ sidebarOpen: false }" class="min-h-screen flex">
            <!-- Sidebar -->
            <aside 
                :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
                class="fixed lg:static inset-y-0 left-0 z-50 w-64 bg-white border-r border-stone-200 transform transition-transform duration-200 ease-in-out lg:translate-x-0 overflow-visible"
            >
                <div class="flex flex-col h-full overflow-visible">
                    <!-- Logo -->
                    <div class="flex items-center justify-between h-16 px-6 border-b border-stone-200">
                        <a href="{{ route('merchant.dashboard') }}" class="flex items-center space-x-2">
                            <x-application-logo class="block h-8 w-auto fill-current text-brand-600" />
                            <span class="text-lg font-semibold text-stone-900">Kawhe</span>
                        </a>
                        <button 
                            @click="sidebarOpen = false"
                            class="lg:hidden text-stone-500 hover:text-stone-700"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Navigation -->
                    <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
                        <a 
                            href="{{ route('merchant.dashboard') }}" 
                            class="flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('merchant.dashboard') ? 'bg-brand-50 text-brand-700' : 'text-stone-700 hover:bg-stone-100' }}"
                        >
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            Dashboard
                        </a>
                        
                        <a 
                            href="{{ route('merchant.stores.index') }}" 
                            class="flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('merchant.stores.*') ? 'bg-brand-50 text-brand-700' : 'text-stone-700 hover:bg-stone-100' }}"
                        >
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            Stores
                        </a>
                        
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
                    <div class="px-4 py-4 border-t border-stone-200 relative" style="overflow: visible;">
                        <x-dropdown align="left" width="48" direction="up">
                            <x-slot name="trigger">
                                <button class="flex items-center w-full px-3 py-2 text-sm font-medium text-stone-700 rounded-lg hover:bg-stone-100 transition-colors">
                                    <div class="flex items-center flex-1">
                                        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-brand-100 flex items-center justify-center">
                                            <span class="text-brand-700 font-semibold text-sm">{{ substr(Auth::user()->name, 0, 1) }}</span>
                                        </div>
                                        <div class="ml-3 text-left">
                                            <div class="text-sm font-medium text-stone-900">{{ Auth::user()->name }}</div>
                                            <div class="text-xs text-stone-500">{{ Auth::user()->email }}</div>
                                        </div>
                                    </div>
                                    <svg class="w-4 h-4 ml-2 text-stone-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                            </x-slot>

                            <x-slot name="content">
                                <x-dropdown-link :href="route('profile.edit')">
                                    {{ __('Profile') }}
                                </x-dropdown-link>

                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <x-dropdown-link :href="route('logout')"
                                            onclick="event.preventDefault();
                                                        this.closest('form').submit();">
                                        {{ __('Log Out') }}
                                    </x-dropdown-link>
                                </form>
                            </x-slot>
                        </x-dropdown>
                    </div>
                </div>
            </aside>

            <!-- Overlay for mobile -->
            <div 
                x-show="sidebarOpen"
                @click="sidebarOpen = false"
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
            <div class="flex-1 flex flex-col overflow-hidden">
                <!-- Top Header -->
                <header class="bg-white border-b border-stone-200">
                    <div class="flex items-center justify-between h-16 px-4 sm:px-6 lg:px-8">
                        <div class="flex items-center">
                            <button 
                                @click="sidebarOpen = true"
                                class="lg:hidden text-stone-500 hover:text-stone-700 mr-4"
                            >
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                </svg>
                            </button>
                            @isset($header)
                                <h1 class="text-xl font-semibold text-stone-900">
                                    {{ $header }}
                                </h1>
                            @endisset
                        </div>
                    </div>
                </header>

                <!-- Page Content -->
                <main class="flex-1 overflow-y-auto">
                    <div class="py-6 px-4 sm:px-6 lg:px-8">
                        <x-flash-messages />
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>
    </body>
</html>
