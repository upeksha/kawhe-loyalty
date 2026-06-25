<x-merchant-layout>
    <x-slot name="header">
        {{ __('Dashboard') }}
    </x-slot>

    @php
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
        $analytics = $analytics ?? [
            'active_customers' => 0,
            'joins_last_window' => 0,
            'rewards_earned_last_window' => 0,
            'rewards_redeemed_last_window' => 0,
            'recent_activity_trend' => collect(),
        ];

        $trendPoints = collect($analytics['recent_activity_trend'] ?? [])->values();
        $trendLabels = $trendPoints->pluck('label')->all();
        $joinSeries = $trendPoints->pluck('joins')->map(fn ($value) => (int) $value)->all();
        $stampSeries = $trendPoints->pluck('stamps')->map(fn ($value) => (int) $value)->all();
        $redeemSeries = $trendPoints->pluck('redeems')->map(fn ($value) => (int) $value)->all();
        $earnedSeries = $trendPoints->map(fn ($day) => (int) (($day['stamps'] ?? 0) + ($day['redeems'] ?? 0)))->all();

        $summaryCards = [
            [
                'label' => 'Active customers',
                'value' => number_format($analytics['active_customers']),
                'tone' => 'bg-[#f3efe7]',
                'accent' => 'text-[#3a2a22]',
                'chart' => [
                    'type' => 'line',
                    'data' => [
                        'labels' => $trendLabels,
                        'datasets' => [[
                            'label' => 'Active customers trend',
                            'data' => $joinSeries,
                            'borderWidth' => 1.8,
                            'pointRadius' => 0,
                            'tension' => 0.5,
                            'borderColor' => 'rgba(58, 42, 34, 0.82)',
                        ]],
                    ],
                    'options' => [
                        'layout' => ['padding' => ['top' => 8, 'right' => 6, 'bottom' => 8, 'left' => 6]],
                        'plugins' => ['tooltip' => ['enabled' => false]],
                        'scales' => [
                            'x' => ['display' => false, 'offset' => true],
                            'y' => ['display' => false, 'grace' => '24%'],
                        ],
                        'elements' => ['line' => ['capBezierPoints' => true]],
                    ],
                ],
            ],
            [
                'label' => 'Rewards earned',
                'value' => number_format($analytics['rewards_earned_last_window']),
                'tone' => 'bg-[#edf4eb]',
                'accent' => 'text-[#1f3b2c]',
                'chart' => [
                    'type' => 'line',
                    'data' => [
                        'labels' => $trendLabels,
                        'datasets' => [[
                            'label' => 'Rewards earned trend',
                            'data' => $earnedSeries,
                            'borderWidth' => 1.8,
                            'pointRadius' => 0,
                            'tension' => 0.5,
                            'borderColor' => 'rgba(31, 59, 44, 0.82)',
                        ]],
                    ],
                    'options' => [
                        'layout' => ['padding' => ['top' => 8, 'right' => 6, 'bottom' => 8, 'left' => 6]],
                        'plugins' => ['tooltip' => ['enabled' => false]],
                        'scales' => [
                            'x' => ['display' => false, 'offset' => true],
                            'y' => ['display' => false, 'grace' => '24%'],
                        ],
                        'elements' => ['line' => ['capBezierPoints' => true]],
                    ],
                ],
            ],
            [
                'label' => 'Rewards redeemed',
                'value' => number_format($analytics['rewards_redeemed_last_window']),
                'tone' => 'bg-[#fff4e9]',
                'accent' => 'text-[#c96a3b]',
                'chart' => [
                    'type' => 'line',
                    'data' => [
                        'labels' => $trendLabels,
                        'datasets' => [[
                            'label' => 'Rewards redeemed trend',
                            'data' => $redeemSeries,
                            'borderWidth' => 1.8,
                            'pointRadius' => 0,
                            'tension' => 0.5,
                            'borderColor' => 'rgba(201, 106, 59, 0.82)',
                        ]],
                    ],
                    'options' => [
                        'layout' => ['padding' => ['top' => 8, 'right' => 6, 'bottom' => 8, 'left' => 6]],
                        'plugins' => ['tooltip' => ['enabled' => false]],
                        'scales' => [
                            'x' => ['display' => false, 'offset' => true],
                            'y' => ['display' => false, 'grace' => '24%'],
                        ],
                        'elements' => ['line' => ['capBezierPoints' => true]],
                    ],
                ],
            ],
        ];

        $loyaltyActivityChart = [
            'type' => 'line',
            'data' => [
                'labels' => $trendLabels,
                'datasets' => [
                    [
                        'label' => 'Joins',
                        'data' => $joinSeries,
                        'borderWidth' => 3,
                        'pointRadius' => 0,
                        'tension' => 0.42,
                        'fill' => true,
                        'backgroundColor' => 'rgba(58, 42, 34, 0.12)',
                        'borderColor' => '#3a2a22',
                    ],
                    [
                        'label' => 'Stamps',
                        'data' => $stampSeries,
                        'borderWidth' => 3,
                        'pointRadius' => 0,
                        'tension' => 0.42,
                        'fill' => true,
                        'backgroundColor' => 'rgba(79, 125, 84, 0.12)',
                        'borderColor' => '#4f7d54',
                    ],
                    [
                        'label' => 'Redeems',
                        'data' => $redeemSeries,
                        'borderWidth' => 3,
                        'pointRadius' => 0,
                        'tension' => 0.42,
                        'fill' => false,
                        'borderColor' => '#d6a24a',
                    ],
                ],
            ],
            'options' => [
                'plugins' => [
                    'legend' => [
                        'display' => true,
                        'position' => 'bottom',
                        'labels' => [
                            'usePointStyle' => true,
                            'pointStyle' => 'circle',
                            'boxWidth' => 8,
                            'boxHeight' => 8,
                            'padding' => 18,
                            'color' => '#57534e',
                        ],
                    ],
                ],
                'scales' => [
                    'x' => [
                        'grid' => ['display' => false],
                        'ticks' => ['color' => '#a8a29e', 'font' => ['size' => 11]],
                        'border' => ['display' => false],
                    ],
                    'y' => [
                        'beginAtZero' => true,
                        'ticks' => [
                            'display' => false,
                            'precision' => 0,
                        ],
                        'grid' => [
                            'color' => '#e7e5e4',
                            'drawTicks' => false,
                        ],
                        'border' => ['display' => false],
                    ],
                ],
            ],
        ];

        $cardGrowthChart = [
            'type' => 'line',
            'data' => [
                'labels' => $trendLabels,
                'datasets' => [[
                    'label' => 'New cards',
                    'data' => $joinSeries,
                    'borderWidth' => 3,
                    'pointRadius' => 0,
                    'tension' => 0.44,
                    'fill' => true,
                    'backgroundColor' => 'rgba(167, 199, 161, 0.18)',
                    'borderColor' => '#4f7d54',
                ]],
            ],
            'options' => [
                'plugins' => [
                    'legend' => [
                        'display' => true,
                        'position' => 'bottom',
                        'labels' => [
                            'usePointStyle' => true,
                            'pointStyle' => 'circle',
                            'boxWidth' => 8,
                            'boxHeight' => 8,
                            'padding' => 18,
                            'color' => '#57534e',
                        ],
                    ],
                ],
                'scales' => [
                    'x' => [
                        'grid' => ['display' => false],
                        'ticks' => ['color' => '#a8a29e', 'font' => ['size' => 11]],
                        'border' => ['display' => false],
                    ],
                    'y' => [
                        'beginAtZero' => true,
                        'ticks' => [
                            'display' => false,
                            'precision' => 0,
                        ],
                        'grid' => [
                            'color' => '#e7e5e4',
                            'drawTicks' => false,
                        ],
                        'border' => ['display' => false],
                    ],
                ],
            ],
        ];

        $miniActivity = $trendPoints->take(-5);
    @endphp

    <div class="space-y-6 sm:space-y-8">
        <x-ui.page-hero
            eyebrow="Dashboard"
            title="Welcome back, {{ request()->user()->name ?? 'Merchant' }}."
            description="Here’s the clearest read on customer growth, reward usage, and store readiness across your loyalty program."
            class="rounded-[28px] border-stone-200/70 p-4 shadow-stone-200/50 sm:p-6"
        >
            <x-slot name="actions">
                <div class="w-full max-w-[34rem]">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-400">Quick Actions</p>
                    <div class="mt-2 grid gap-2 sm:grid-cols-3">
                        <x-ui.action-tile href="{{ route('merchant.scanner') }}" label="Open Scanner" tone="sand" />
                        <x-ui.action-tile href="{{ route('merchant.stores.index') }}" label="Store QR" tone="sage" />
                        <x-ui.action-tile href="{{ route('merchant.customers.index') }}" label="Customers" tone="peach" />
                    </div>
                </div>
            </x-slot>
        </x-ui.page-hero>

        <section class="grid gap-4 lg:grid-cols-3">
            @foreach($summaryCards as $card)
                <x-ui.stat-card
                    layout="horizontal"
                    :label="$card['label']"
                    :value="$card['value']"
                    :tone="$card['tone']"
                    :accent="$card['accent']"
                    size="lg"
                    class="rounded-[26px] border border-stone-200/60 shadow-sm shadow-stone-200/40"
                >
                    <x-slot name="chart">
                        <canvas class="h-full w-full" data-chart='@json($card["chart"])' aria-hidden="true"></canvas>
                    </x-slot>
                </x-ui.stat-card>
            @endforeach
        </section>

        <section class="grid gap-6 md:grid-cols-2">
            <x-ui.section-panel class="rounded-[30px] border-stone-200/70 p-5 shadow-stone-200/50 sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-xl font-semibold text-stone-900">Loyalty Activity</h3>
                        <p class="mt-2 text-sm leading-6 text-stone-600">Daily joins, stamps, and redeems across the last 14 days.</p>
                    </div>
                    <x-ui.badge variant="default">Last 14 days</x-ui.badge>
                </div>

                <div class="mt-6 grid gap-3 md:grid-cols-3">
                    <x-ui.admin-metric label="New cards" :value="number_format($analytics['joins_last_window'])" tone="bg-[#f3efe7] text-[#3a2a22]" />
                    <x-ui.admin-metric label="Stamp activity" :value="number_format($trendPoints->sum('stamps'))" tone="bg-[#edf4eb] text-[#1f3b2c]" />
                    <x-ui.admin-metric label="Avg per day" :value="number_format($trendPoints->sum('total') / max(1, $trendPoints->count()), 1)" tone="bg-[#fff5df] text-[#d6a24a]" />
                </div>

                @if($trendPoints->every(fn ($day) => ($day['total'] ?? 0) === 0))
                    <div class="mt-6 rounded-2xl border border-dashed border-stone-200 bg-stone-50/70 p-5 text-sm text-stone-600">
                        No recent join or card activity yet. As customers join and start collecting stamps, your activity graph will appear here.
                    </div>
                @else
                    <div class="mt-6 rounded-[24px] border border-stone-200/70 bg-stone-50/70 p-4 sm:p-5">
                        <div class="h-[20rem] w-full">
                            <canvas class="h-full w-full" data-chart='@json($loyaltyActivityChart)' aria-label="Loyalty activity chart"></canvas>
                        </div>
                    </div>
                @endif
            </x-ui.section-panel>

            <x-ui.section-panel class="rounded-[30px] border-stone-200/70 p-5 shadow-stone-200/50 sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-xl font-semibold text-stone-900">Card Growth</h3>
                        <p class="mt-2 text-sm leading-6 text-stone-600">New customer cards added over the last two weeks.</p>
                    </div>
                    <x-ui.badge variant="success">Live</x-ui.badge>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <x-ui.admin-metric label="Cards added" :value="number_format($analytics['joins_last_window'])" tone="bg-[#f3efe7] text-[#3a2a22]" />
                    <x-ui.admin-metric label="Daily average" :value="number_format($analytics['joins_last_window'] / max(1, $trendPoints->count()), 1)" tone="bg-stone-100 text-stone-700" />
                </div>

                @if($trendPoints->every(fn ($day) => ($day['joins'] ?? 0) === 0))
                    <div class="mt-6 rounded-2xl border border-dashed border-stone-200 bg-stone-50/70 p-5 text-sm text-stone-600">
                        No new customer cards in the last 14 days yet.
                    </div>
                @else
                    <div class="mt-6 rounded-[24px] border border-stone-200 bg-stone-50/70 p-4">
                        <div class="h-64 w-full">
                            <canvas class="h-full w-full" data-chart='@json($cardGrowthChart)' aria-label="Card growth chart"></canvas>
                        </div>
                    </div>
                @endif
            </x-ui.section-panel>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            @if(isset($usageStats))
                <x-ui.card class="!rounded-[30px] !border-stone-200/70 p-5 sm:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base sm:text-lg font-bold text-stone-900">Plan Health</h3>
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
                    @php
                        $storesUsed = (int) ($usageStats['stores_count'] ?? 0);
                        $storesLimit = $usageStats['stores_limit'] ?? null;
                        $cardsUsed = (int) ($usageStats['primary_store_programs_count'] ?? $usageStats['programs_count'] ?? 0);
                        $cardsLimit = $usageStats['programs_per_store_limit'] ?? null;
                        $customersUsed = (int) ($usageStats['primary_program_customers_count'] ?? 0);
                        $customersLimit = $usageStats['customers_per_program_limit'] ?? null;
                    @endphp
                    <div class="mt-4 space-y-3">
                        <div class="rounded-xl border border-stone-200 bg-stone-50/80 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Stores</p>
                            <p class="mt-2 text-lg font-semibold text-stone-900">
                                {{ $storesUsed }}@if($storesLimit)<span class="text-sm font-medium text-stone-500"> / {{ $storesLimit }}</span>@endif
                            </p>
                            <p class="mt-1 text-xs text-stone-600">{{ ($usageStats['can_create_store'] ?? false) ? 'Room for another store' : 'Store limit reached' }}</p>
                            <x-ui.usage-meter class="mt-3" :used="$storesUsed" :limit="$storesLimit" />
                        </div>

                        <div class="rounded-xl border border-stone-200 bg-stone-50/80 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Cards (primary store)</p>
                            <p class="mt-2 text-lg font-semibold text-stone-900">
                                {{ $cardsUsed }}@if($cardsLimit)<span class="text-sm font-medium text-stone-500"> / {{ $cardsLimit }}</span>@endif
                            </p>
                            <p class="mt-1 text-xs text-stone-600">{{ ($usageStats['can_create_program'] ?? false) ? 'Can add another card' : 'Card limit reached on this store' }}</p>
                            <x-ui.usage-meter class="mt-3" :used="$cardsUsed" :limit="$cardsLimit" />
                        </div>

                        <div class="rounded-xl border border-stone-200 bg-stone-50/80 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Customers (primary card)</p>
                            <p class="mt-2 text-lg font-semibold text-stone-900">
                                {{ $customersUsed }}@if($customersLimit)<span class="text-sm font-medium text-stone-500"> / {{ $customersLimit }}</span>@else<span class="text-sm font-medium text-stone-500"> unlimited</span>@endif
                            </p>
                            <p class="mt-1 text-xs text-stone-600">{{ $usageStats['is_subscribed'] ? 'Unlimited new joins on Pro' : 'New joins blocked at 100 on Free' }}</p>
                            <x-ui.usage-meter class="mt-3" :used="$customersUsed" :limit="$customersLimit" />
                        </div>

                        @if($usageStats['grandfathered_programs_count'] > 0)
                            <p class="px-1 text-xs text-stone-500">{{ $usageStats['grandfathered_programs_count'] }} grandfathered card(s) active</p>
                        @endif

                        <x-ui.button href="{{ route('billing.index') }}" variant="ghost" size="sm" class="w-full">
                            View billing details
                        </x-ui.button>
                    </div>
                </x-ui.card>
            @else
                <x-ui.card class="p-5 sm:p-6">
                    <h3 class="text-base sm:text-lg font-bold text-stone-900">Plan Health</h3>
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

            <x-ui.card class="!rounded-[30px] !border-stone-200/70 p-5 sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base sm:text-lg font-bold text-stone-900">Wallet Readiness</h3>
                        <p class="mt-1 text-sm text-stone-600">How ready your stores are for Apple Wallet and Google Wallet presentation.</p>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold text-stone-700">
                        {{ $walletReadyCount }}/{{ max(1, $storesCount) }} ready
                    </span>
                </div>

                <div class="mt-5 space-y-3 text-sm">
                    <x-ui.readiness-row
                        label="Store branding added"
                        :value="$storesWithLogo.'/'.$storesCount"
                        :state="$storesWithLogo === $storesCount && $storesCount > 0 ? 'ready' : 'attention'"
                    />
                    <x-ui.readiness-row
                        label="Wallet assets added"
                        :value="$storesWithWalletAssets.'/'.$storesCount"
                        :state="$storesWithWalletAssets === $storesCount && $storesCount > 0 ? 'ready' : 'attention'"
                    />
                    <x-ui.readiness-row
                        label="Reward setup complete"
                        :value="$storesWithRewardRules.'/'.$storesCount"
                        :state="$storesWithRewardRules === $storesCount && $storesCount > 0 ? 'ready' : 'attention'"
                    />
                </div>
            </x-ui.card>

            <x-ui.card class="!rounded-[30px] !border-stone-200/70 p-5 sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base sm:text-lg font-bold text-stone-900">Recent Activity</h3>
                        <p class="mt-1 text-sm text-stone-600">A compact summary of your latest customer activity.</p>
                    </div>
                </div>

                <div class="mt-5 space-y-3">
                    @forelse($miniActivity as $day)
                        <div class="flex items-center justify-between gap-3 rounded-2xl border border-stone-200/70 bg-stone-50/70 px-3 py-3">
                            <div>
                                <p class="text-sm font-semibold text-stone-900">{{ $day['label'] }}</p>
                                <p class="text-xs text-stone-500">{{ $day['joins'] }} joins, {{ $day['stamps'] }} stamps, {{ $day['redeems'] }} redeems</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-stone-900">{{ $day['total'] }}</p>
                                <p class="text-[11px] text-stone-400">events</p>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-stone-200 bg-stone-50/70 p-4 text-sm text-stone-500">No recent activity yet.</div>
                    @endforelse
                </div>
            </x-ui.card>
        </section>

        @if(isset($usageStats) && !$usageStats['is_subscribed'])
            @if(($usageStats['primary_program_customers_count'] ?? 0) >= ($usageStats['customers_per_program_limit'] ?? PHP_INT_MAX))
                <x-ui.alert variant="warning">
                    <strong>Customer limit reached:</strong> New joins are paused at 100 customers on this card. Existing customers can still scan and redeem.
                </x-ui.alert>
            @elseif($usageStats['has_cancelled_subscription'] && $usageStats['grandfathered_programs_count'] > 0)
                <x-ui.alert variant="warning">
                    <strong>Grandfathered cards:</strong> You have {{ $usageStats['grandfathered_programs_count'] }} active from your previous Pro subscription. New growth follows free-plan limits.
                </x-ui.alert>
            @elseif(($usageStats['customers_usage_percentage'] ?? 0) >= 80)
                <x-ui.alert variant="warning">
                    <strong>Almost at customer cap:</strong> {{ $usageStats['primary_program_customers_count'] }} of {{ $usageStats['customers_per_program_limit'] }} free-plan customers on your primary card.
                </x-ui.alert>
            @endif
        @endif
    </div>
</x-merchant-layout>
