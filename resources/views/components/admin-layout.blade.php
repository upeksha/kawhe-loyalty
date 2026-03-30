@props(['header' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }} - Admin</title>

        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('scripts')
    </head>
    <body class="bg-stone-100 font-sans antialiased text-stone-900">
        <div class="min-h-screen lg:flex">
            <aside class="hidden w-72 shrink-0 border-r border-stone-200/80 bg-gradient-to-b from-white via-white to-stone-50 lg:flex lg:flex-col">
                <div class="px-6 pb-6 pt-8">
                    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-brand-50 text-brand-600 shadow-sm shadow-brand-100/80">
                            <x-application-logo class="block h-7 w-auto fill-current text-brand-600" />
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-stone-400">Control</p>
                            <p class="text-lg font-semibold text-stone-900">Kawhe Admin</p>
                        </div>
                    </a>

                    <div class="mt-8 rounded-3xl border border-stone-200 bg-stone-50/90 p-4 shadow-sm shadow-stone-200/60">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-stone-400">Signed in</p>
                        <p class="mt-2 text-sm font-semibold text-stone-900">{{ auth()->user()->name ?? 'Admin' }}</p>
                        <p class="mt-1 text-sm text-stone-500">{{ auth()->user()->email ?? '' }}</p>
                    </div>
                </div>

                <nav class="flex-1 px-4 pb-6">
                    <div class="space-y-2 rounded-[28px] bg-white p-3 shadow-sm shadow-stone-200/70 ring-1 ring-stone-200/70">
                        <a
                            href="{{ route('admin.dashboard') }}"
                            class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium transition {{ request()->routeIs('admin.dashboard') ? 'bg-brand-50 text-brand-700 shadow-sm shadow-brand-100/80' : 'text-stone-600 hover:bg-stone-50 hover:text-stone-900' }}"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0h6" />
                            </svg>
                            Dashboard
                        </a>
                        <a
                            href="{{ route('admin.support.index') }}"
                            class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium transition {{ request()->routeIs('admin.support.*') ? 'bg-brand-50 text-brand-700 shadow-sm shadow-brand-100/80' : 'text-stone-600 hover:bg-stone-50 hover:text-stone-900' }}"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16h6M12 3C7.03 3 3 6.582 3 11c0 2.014.836 3.854 2.216 5.263L4 21l4.237-1.12A10.47 10.47 0 0012 20c4.97 0 9-3.582 9-8s-4.03-9-9-9z" />
                            </svg>
                            Support Logs
                        </a>
                    </div>
                </nav>

                <div class="px-6 pb-8">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center justify-center rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm font-medium text-stone-600 transition hover:border-stone-300 hover:text-stone-900">
                            Log out
                        </button>
                    </form>
                </div>
            </aside>

            <div class="flex-1">
                <header class="border-b border-stone-200/80 bg-white/90 backdrop-blur">
                    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-5 sm:px-6 lg:px-8">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-stone-400">Admin workspace</p>
                            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-stone-900">{{ $header ?? '' }}</h1>
                        </div>

                        <div class="flex items-center gap-3">
                            <a href="{{ route('admin.support.index') }}" class="hidden rounded-full border border-stone-200 bg-stone-50 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-brand-200 hover:text-brand-700 sm:inline-flex">
                                Open support logs
                            </a>
                            <div class="rounded-full bg-brand-50 px-4 py-2 text-sm font-medium text-brand-700">
                                {{ auth()->user()->email ?? '' }}
                            </div>
                        </div>
                    </div>
                </header>

                <main class="px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
