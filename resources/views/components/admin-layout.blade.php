@props(['header' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }} - Admin</title>

        <x-favicon />
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('scripts')
    </head>
    <body
        class="bg-stone-100 font-sans antialiased text-stone-900"
        x-data="{ sidebarOpen: false }"
        @close-admin-sidebar.window="sidebarOpen = false"
        @keydown.escape.window="sidebarOpen = false"
    >
        <div class="min-h-screen lg:flex">
            <div
                x-show="sidebarOpen"
                x-cloak
                class="fixed inset-0 z-40 bg-stone-900/40 lg:hidden"
                @click="sidebarOpen = false"
            ></div>

            <aside
                :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
                class="fixed inset-y-0 left-0 z-50 flex w-72 shrink-0 flex-col border-r border-stone-200/80 bg-gradient-to-b from-white via-white to-stone-50 transition-transform duration-200 ease-in-out lg:static lg:translate-x-0"
            >
                <div class="flex items-center justify-between border-b border-stone-200/80 px-4 py-4 lg:hidden">
                    <p class="text-sm font-semibold text-stone-900">Admin menu</p>
                    <button
                        type="button"
                        class="rounded-xl p-2 text-stone-500 hover:bg-stone-100 hover:text-stone-700"
                        @click="sidebarOpen = false"
                        aria-label="Close menu"
                    >
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <x-admin.sidebar />
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header class="border-b border-stone-200/80 bg-white/90 backdrop-blur">
                    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-5 sm:px-6 lg:px-8">
                        <div class="flex min-w-0 items-center gap-3">
                            <button
                                type="button"
                                class="rounded-xl border border-stone-200 bg-white p-2 text-stone-600 hover:bg-stone-50 lg:hidden"
                                @click="sidebarOpen = true"
                                aria-label="Open menu"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                </svg>
                            </button>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-stone-400">Admin workspace</p>
                                <h1 class="mt-1 truncate text-2xl font-semibold tracking-tight text-stone-900">{{ $header ?? '' }}</h1>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-3">
                            <a href="{{ route('admin.support.index') }}" class="hidden rounded-full border border-stone-200 bg-stone-50 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-brand-200 hover:text-brand-700 sm:inline-flex">
                                Open support logs
                            </a>
                            <div class="hidden rounded-full bg-brand-50 px-4 py-2 text-sm font-medium text-brand-700 sm:block">
                                {{ auth()->user()->email ?? '' }}
                            </div>
                        </div>
                    </div>
                </header>

                <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    <x-flash-messages />
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
