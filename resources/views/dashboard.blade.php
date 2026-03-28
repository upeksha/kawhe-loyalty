<x-merchant-layout>
    <x-slot name="header">
        {{ __('Dashboard') }}
    </x-slot>

    @php
        $usagePercent = isset($usageStats) ? min(100, max(0, (int) ($usageStats['usage_percentage'] ?? 0))) : 0;
        $cardsRemaining = isset($usageStats) && !($usageStats['is_subscribed'] ?? false)
            ? max(0, (int) (($usageStats['limit'] ?? 0) - ($usageStats['non_grandfathered_count'] ?? 0)))
            : null;
        $merchantStores = request()->user()?->stores()->get() ?? collect();
        $storesCount = $merchantStores->count();
        $storesWithLogo = $merchantStores->filter(fn ($store) => !empty($store->logo_path))->count();
        $storesWithWalletAssets = $merchantStores->filter(fn ($store) => !empty($store->pass_logo_path) || !empty($store->pass_hero_image_path))->count();
        $storesWithRewardRules = $merchantStores->filter(fn ($store) => !empty($store->reward_title) && (int) ($store->reward_target ?? 0) > 0)->count();
        $walletReadyCount = $merchantStores->filter(function ($store) {
            return !empty($store->reward_title)
                && (int) ($store->reward_target ?? 0) > 0
                && !empty($store->background_color)
                && (!empty($store->logo_path) || !empty($store->pass_logo_path));
        })->count();
    @endphp

    <div class="space-y-4 sm:space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <x-ui.card class="p-4 sm:p-6 lg:col-span-2">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h2 class="text-base sm:text-lg font-bold text-stone-900">Today’s Quick Actions</h2>
                        <p class="text-sm text-stone-600 mt-1">Open scanner, share your join QR, or manage customers.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-ui.button href="{{ route('merchant.scanner') }}" variant="primary" size="sm">
                            Open Scanner
                        </x-ui.button>
                        <x-ui.button href="{{ route('merchant.stores.index') }}" variant="secondary" size="sm">
                            Store QR
                        </x-ui.button>
                        <x-ui.button href="{{ route('merchant.customers.index') }}" variant="secondary" size="sm">
                            Customers
                        </x-ui.button>
                    </div>
                </div>
            </x-ui.card>

            @if(isset($usageStats))
                <x-ui.card class="p-4 sm:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-sm sm:text-base font-bold text-stone-900">Plan Health</h3>
                            <p class="mt-1 text-xs text-stone-600">
                                @if($usageStats['is_subscribed'])
                                    Pro plan active
                                @else
                                    Free plan usage
                                @endif
                            </p>
                        </div>
                        @if(!$usageStats['is_subscribed'])
                            <x-ui.button href="{{ route('billing.index') }}" variant="primary" size="sm">
                                Upgrade
                            </x-ui.button>
                        @endif
                    </div>
                    <div class="mt-4 rounded-xl border border-stone-200 bg-stone-50/80 p-4">
                        <p class="text-sm text-stone-700">
                            Cards issued:
                            <strong>{{ $usageStats['cards_count'] }} / {{ $usageStats['is_subscribed'] ? '∞' : $usageStats['limit'] }}</strong>
                        </p>
                        @if(!$usageStats['is_subscribed'])
                            <p class="mt-1 text-xs text-stone-500">Remaining on free plan: {{ $cardsRemaining }}</p>
                            <div class="mt-3 w-full bg-stone-200 rounded-full h-2">
                                <div class="bg-brand-600 h-2 rounded-full transition-all duration-300" style="width: {{ $usagePercent }}%"></div>
                            </div>
                        @endif
                        @if($usageStats['grandfathered_count'] > 0)
                            <p class="mt-2 text-xs text-stone-500">{{ $usageStats['grandfathered_count'] }} grandfathered card(s) active</p>
                        @endif
                    </div>
                </x-ui.card>
            @else
                <x-ui.card class="p-4 sm:p-6">
                    <h3 class="text-sm sm:text-base font-bold text-stone-900">Plan Health</h3>
                    <p class="mt-1 text-xs text-stone-600">Usage metrics are temporarily unavailable.</p>
                    <div class="mt-4 space-y-3" aria-hidden="true">
                        <div class="h-3 rounded bg-stone-200 animate-pulse"></div>
                        <div class="h-3 w-2/3 rounded bg-stone-200 animate-pulse"></div>
                    </div>
                    <x-ui.button href="{{ route('merchant.dashboard') }}" variant="secondary" size="sm" class="mt-4">
                        Refresh
                    </x-ui.button>
                </x-ui.card>
            @endif
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-ui.card class="p-4 sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base sm:text-lg font-bold text-stone-900">Wallet Readiness</h3>
                        <p class="mt-1 text-sm text-stone-600">A quick view of how ready your stores are for Apple Wallet and Google Wallet presentation.</p>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold text-stone-700">
                        {{ $walletReadyCount }}/{{ max(1, $storesCount) }} launch-ready
                    </span>
                </div>

                <div class="mt-5 space-y-3 text-sm">
                    <div class="flex items-center justify-between rounded-xl border border-stone-200 bg-stone-50/80 px-4 py-3">
                        <span class="text-stone-700">Store branding added</span>
                        <span class="font-medium {{ $storesWithLogo === $storesCount && $storesCount > 0 ? 'text-emerald-700' : 'text-amber-700' }}">
                            {{ $storesWithLogo }}/{{ $storesCount }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between rounded-xl border border-stone-200 bg-stone-50/80 px-4 py-3">
                        <span class="text-stone-700">Wallet assets added</span>
                        <span class="font-medium {{ $storesWithWalletAssets === $storesCount && $storesCount > 0 ? 'text-emerald-700' : 'text-amber-700' }}">
                            {{ $storesWithWalletAssets }}/{{ $storesCount }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between rounded-xl border border-stone-200 bg-stone-50/80 px-4 py-3">
                        <span class="text-stone-700">Reward setup complete</span>
                        <span class="font-medium {{ $storesWithRewardRules === $storesCount && $storesCount > 0 ? 'text-emerald-700' : 'text-amber-700' }}">
                            {{ $storesWithRewardRules }}/{{ $storesCount }}
                        </span>
                    </div>
                </div>

                <p class="mt-4 text-xs leading-relaxed text-stone-500">
                    A store counts as wallet-ready when it has reward settings, background styling, and at least one usable logo source for pass generation.
                </p>
            </x-ui.card>

            <x-ui.card class="p-4 sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base sm:text-lg font-bold text-stone-900">Billing Readiness</h3>
                        <p class="mt-1 text-sm text-stone-600">Make sure new customers can keep joining without an unexpected plan limit.</p>
                    </div>
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ isset($usageStats) && ($usageStats['is_subscribed'] ?? false) ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-100 text-stone-700' }}">
                        @if(isset($usageStats) && ($usageStats['is_subscribed'] ?? false))
                            Pro active
                        @else
                            Free plan
                        @endif
                    </span>
                </div>

                <div class="mt-5 space-y-3 text-sm">
                    <div class="flex items-center justify-between rounded-xl border border-stone-200 bg-stone-50/80 px-4 py-3">
                        <span class="text-stone-700">Current plan</span>
                        <span class="font-medium text-stone-900">
                            @if(isset($usageStats) && ($usageStats['is_subscribed'] ?? false))
                                Paid
                            @else
                                Free
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center justify-between rounded-xl border border-stone-200 bg-stone-50/80 px-4 py-3">
                        <span class="text-stone-700">Cards issued</span>
                        <span class="font-medium text-stone-900">
                            {{ $usageStats['cards_count'] ?? 0 }} / {{ isset($usageStats) && ($usageStats['is_subscribed'] ?? false) ? '∞' : ($usageStats['limit'] ?? 0) }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between rounded-xl border border-stone-200 bg-stone-50/80 px-4 py-3">
                        <span class="text-stone-700">Can add more customers</span>
                        <span class="font-medium {{ isset($usageStats) && ($usageStats['can_create_card'] ?? false) ? 'text-emerald-700' : 'text-accent-700' }}">
                            {{ isset($usageStats) && ($usageStats['can_create_card'] ?? false) ? 'Yes' : 'Needs plan review' }}
                        </span>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <x-ui.button href="{{ route('billing.index') }}" variant="secondary" size="sm">
                        Open Billing
                    </x-ui.button>
                    <x-ui.button href="{{ route('merchant.stores.index') }}" variant="ghost" size="sm">
                        Review Stores
                    </x-ui.button>
                </div>
            </x-ui.card>
        </div>

        @if(isset($usageStats) && !$usageStats['is_subscribed'])
            @if($usageStats['non_grandfathered_count'] >= $usageStats['limit'])
                <x-ui.card class="p-4 border border-accent-200 bg-accent-50">
                    <p class="text-sm text-accent-800">
                        <strong>Limit Reached:</strong> You’ve reached the free plan limit of {{ $usageStats['limit'] }} cards.
                        @if($usageStats['grandfathered_count'] > 0)
                            {{ $usageStats['grandfathered_count'] }} grandfathered card(s) remain active, but new customers cannot join until you upgrade.
                        @else
                            Existing customers can still use their cards, but new customers cannot join until you upgrade.
                        @endif
                    </p>
                </x-ui.card>
            @elseif($usageStats['has_cancelled_subscription'] && $usageStats['grandfathered_count'] > 0)
                <x-ui.card class="p-4 border border-brand-200 bg-brand-50">
                    <p class="text-sm text-brand-800">
                        <strong>Grandfathered Cards:</strong> You have {{ $usageStats['grandfathered_count'] }} active from your previous Pro subscription.
                        You can create {{ $usageStats['limit'] - $usageStats['non_grandfathered_count'] }} more card(s) on free.
                    </p>
                </x-ui.card>
            @elseif($usageStats['cards_count'] >= ($usageStats['limit'] * 0.8))
                <x-ui.card class="p-4 border border-brand-200 bg-brand-50">
                    <p class="text-sm text-brand-800">
                        <strong>Almost there:</strong> You’re using {{ $usageStats['cards_count'] }} of {{ $usageStats['limit'] }} free cards.
                        Consider upgrading to keep adding customers.
                    </p>
                </x-ui.card>
            @endif
        @endif

        <div>
            <h3 class="text-xs sm:text-sm font-semibold uppercase tracking-wide text-stone-500 mb-3">Manage Your Workspace</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <x-ui.card class="p-4 sm:p-6">
                    <div class="flex items-center mb-4">
                        <div class="flex-shrink-0 w-12 h-12 bg-brand-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                            </svg>
                        </div>
                        <h3 class="text-base sm:text-lg font-bold ml-3 text-stone-900">Scanner</h3>
                    </div>
                    <p class="mb-4 text-stone-600">Scan customer QR codes to stamp cards quickly.</p>
                    <x-ui.button href="{{ route('merchant.scanner') }}" variant="primary" size="sm">
                        Open Scanner
                    </x-ui.button>
                </x-ui.card>

                <x-ui.card class="p-4 sm:p-6">
                    <div class="flex items-center mb-4">
                        <div class="flex-shrink-0 w-12 h-12 bg-brand-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                        <h3 class="text-base sm:text-lg font-bold ml-3 text-stone-900">My Stores</h3>
                    </div>
                    <p class="mb-4 text-stone-600">Manage rewards, branding, and join QR codes.</p>
                    <div class="flex gap-2">
                        <x-ui.button href="{{ route('merchant.stores.index') }}" variant="primary" size="sm">
                            Manage Stores
                        </x-ui.button>
                        <x-ui.button href="{{ route('merchant.stores.create') }}" variant="secondary" size="sm">
                            Add Store
                        </x-ui.button>
                    </div>
                </x-ui.card>

                <x-ui.card class="p-4 sm:p-6">
                    <div class="flex items-center mb-4">
                        <div class="flex-shrink-0 w-12 h-12 bg-brand-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </div>
                        <h3 class="text-base sm:text-lg font-bold ml-3 text-stone-900">Customers</h3>
                    </div>
                    <p class="mb-4 text-stone-600">View customers across all stores and update details.</p>
                    <x-ui.button href="{{ route('merchant.customers.index') }}" variant="primary" size="sm">
                        View Customers
                    </x-ui.button>
                </x-ui.card>
            </div>
        </div>
    </div>
</x-merchant-layout>
