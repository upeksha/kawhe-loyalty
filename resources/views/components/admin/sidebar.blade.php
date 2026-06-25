<div class="flex h-full min-h-0 flex-col">
    <div class="px-6 pb-6 pt-8">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3" @click="$dispatch('close-admin-sidebar')">
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

    <nav class="flex-1 overflow-y-auto px-4 pb-6">
        <div class="space-y-2 rounded-[28px] bg-white p-3 shadow-sm shadow-stone-200/70 ring-1 ring-stone-200/70">
            <a
                href="{{ route('admin.dashboard') }}"
                @click="$dispatch('close-admin-sidebar')"
                class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium transition {{ request()->routeIs('admin.dashboard') ? 'bg-brand-50 text-brand-700 shadow-sm shadow-brand-100/80' : 'text-stone-600 hover:bg-stone-50 hover:text-stone-900' }}"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0h6" />
                </svg>
                Dashboard
            </a>
            <a
                href="{{ route('admin.support.index') }}"
                @click="$dispatch('close-admin-sidebar')"
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
</div>
