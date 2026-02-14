@props(['header' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }} - Admin</title>

        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('scripts')
    </head>
    <body class="font-sans antialiased bg-stone-50">
        <div class="min-h-screen flex">
            <!-- Sidebar -->
            <aside class="w-64 bg-white border-r border-stone-200">
                <div class="flex items-center h-16 px-6 border-b border-stone-200">
                    <a href="{{ route('admin.dashboard') }}" class="flex items-center space-x-2">
                        <x-application-logo class="block h-8 w-auto fill-current text-brand-600" />
                        <span class="text-lg font-semibold text-stone-900">Kawhe Admin</span>
                    </a>
                </div>

                <nav class="px-4 py-6 space-y-1">
                    <a
                        href="{{ route('admin.dashboard') }}"
                        class="flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('admin.dashboard') ? 'bg-brand-50 text-brand-700' : 'text-stone-700 hover:bg-stone-100' }}"
                    >
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        Dashboard
                    </a>
                </nav>
            </aside>

            <!-- Main content -->
            <div class="flex-1">
                <header class="bg-white border-b border-stone-200 h-16 flex items-center justify-between px-6">
                    <div class="text-lg font-semibold text-stone-900">
                        {{ $header ?? '' }}
                    </div>

                    <div class="flex items-center gap-4">
                        <span class="text-sm text-stone-600">{{ auth()->user()->email ?? '' }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="text-sm text-stone-600 hover:text-stone-800">
                                Logout
                            </button>
                        </form>
                    </div>
                </header>

                <main class="py-8 px-6">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
