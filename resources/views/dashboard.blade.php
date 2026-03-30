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
                'caption' => 'Joined or used their card in the last 30 days',
                'tone' => 'bg-[#f3efe7]',
                'accent' => 'text-[#3a2a22]',
                'chart' => [
                    'type' => 'line',
                    'data' => [
                        'labels' => $trendLabels,
                        'datasets' => [[
                            'label' => 'Active customers trend',
                            'data' => $joinSeries,
                            'borderWidth' => 2.5,
                            'pointRadius' => 0,
                            'tension' => 0.42,
                            'borderColor' => '#3a2a22',
                        ]],
                    ],
                    'options' => [
                        'plugins' => ['tooltip' => ['enabled' => false]],
                        'scales' => ['x' => ['display' => false], 'y' => ['display' => false]],
                        'elements' => ['line' => ['capBezierPoints' => true]],
                    ],
                ],
            ],
            [
                'label' => 'Rewards earned',
                'value' => number_format($analytics['rewards_earned_last_window']),
                'caption' => 'Completed reward cycles in the last 30 days',
                'tone' => 'bg-[#edf4eb]',
                'accent' => 'text-[#1f3b2c]',
                'chart' => [
                    'type' => 'line',
                    'data' => [
                        'labels' => $trendLabels,
                        'datasets' => [[
                            'label' => 'Rewards earned trend',
                            'data' => $earnedSeries,
                            'borderWidth' => 2.5,
                            'pointRadius' => 0,
                            'tension' => 0.42,
                            'borderColor' => '#1f3b2c',
                        ]],
                    ],
                    'options' => [
                        'plugins' => ['tooltip' => ['enabled' => false]],
                        'scales' => ['x' => ['display' => false], 'y' => ['display' => false]],
                        'elements' => ['line' => ['capBezierPoints' => true]],
                    ],
                ],
            ],
            [
                'label' => 'Rewards redeemed',
                'value' => number_format($analytics['rewards_redeemed_last_window']),
                'caption' => 'Redeemed across your stores in the last 30 days',
                'tone' => 'bg-[#fff4e9]',
                'accent' => 'text-[#c96a3b]',
                'chart' => [
                    'type' => 'line',
                    'data' => [
                        'labels' => $trendLabels,
                        'datasets' => [[
                            'label' => 'Rewards redeemed trend',
                            'data' => $redeemSeries,
                            'borderWidth' => 2.5,
                            'pointRadius' => 0,
                            'tension' => 0.42,
                            'borderColor' => '#c96a3b',
                        ]],
                    ],
                    'options' => [
                        'plugins' => ['tooltip' => ['enabled' => false]],
                        'scales' => ['x' => ['display' => false], 'y' => ['display' => false]],
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
        <section class="rounded-[28px] border border-stone-200/70 bg-white p-4 shadow-sm shadow-stone-200/50 sm:p-6">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-2xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#4f7d54]">Dashboard</p>
                    <h2 class="mt-2 text-2xl font-semibold tracking-tight text-stone-900 sm:text-3xl">
                        Welcome back, {{ request()->user()->name ?? 'Merchant' }}.
                    </h2>
                    <p class="mt-2 max-w-xl text-sm leading-6 text-stone-600">
                        Here’s the clearest read on customer growth, reward usage, and store readiness across your loyalty program.
                    </p>
                </div>

                <div class="w-full max-w-[34rem]">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-400">Quick Actions</p>
                    <div class="mt-2 grid gap-2 sm:grid-cols-3">
                        <x-ui.button href="{{ route('merchant.scanner') }}" variant="primary" size="sm" class="!justify-start !rounded-xl !border-0 !bg-[#f3efe7] !px-3 !py-3 !text-[#3a2a22] !shadow-none hover:!bg-[#ece4d6]">
                            Open Scanner
                        </x-ui.button>
                        <x-ui.button href="{{ route('merchant.stores.index') }}" variant="secondary" size="sm" class="!justify-start !rounded-xl !border-0 !bg-[#edf4eb] !px-3 !py-3 !text-[#1f3b2c] !shadow-none hover:!bg-[#deebda]">
                            Store QR
                        </x-ui.button>
                        <x-ui.button href="{{ route('merchant.customers.index') }}" variant="secondary" size="sm" class="!justify-start !rounded-xl !border-0 !bg-[#fff4e9] !px-3 !py-3 !text-[#c96a3b] !shadow-none hover:!bg-[#fde8d7]">
                            Customers
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-3">
            @foreach($summaryCards as $card)
                <article class="rounded-[26px] border border-stone-200/60 {{ $card['tone'] }} p-5 shadow-sm shadow-stone-200/40">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-stone-500">{{ $card['label'] }}</p>
                            <p class="mt-4 text-4xl font-semibold tracking-tight text-stone-950">{{ $card['value'] }}</p>
                            <p class="mt-3 max-w-[16rem] text-sm leading-6 text-stone-600">{{ $card['caption'] }}</p>
                        </div>
                        <div class="mt-2 h-16 w-28 shrink-0">
                            <canvas class="h-full w-full" data-chart='@json($card["chart"])' aria-hidden="true"></canvas>
                        </div>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="grid gap-6 md:grid-cols-2">
            <section class="rounded-[30px] border border-stone-200/70 bg-white p-5 shadow-sm shadow-stone-200/50 sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-xl font-semibold text-stone-900">Loyalty Activity</h3>
                        <p class="mt-2 text-sm leading-6 text-stone-600">Daily joins, stamps, and redeems across the last 14 days.</p>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold text-stone-600">Last 14 days</span>
                </div>

                <div class="mt-6 grid gap-3 md:grid-cols-3">
                    <div class="rounded-2xl bg-[#f3efe7] p-4 text-[#3a2a22]">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] opacity-75">New cards</p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight text-stone-950">{{ number_format($analytics['joins_last_window']) }}</p>
                    </div>
                    <div class="rounded-2xl bg-[#edf4eb] p-4 text-[#1f3b2c]">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] opacity-75">Stamp activity</p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight text-stone-950">{{ number_format($trendPoints->sum('stamps')) }}</p>
                    </div>
                    <div class="rounded-2xl bg-[#fff5df] p-4 text-[#d6a24a]">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] opacity-75">Avg per day</p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight text-stone-950">{{ number_format($trendPoints->sum('total') / max(1, $trendPoints->count()), 1) }}</p>
                    </div>
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
            </section>

            <section class="rounded-[30px] border border-stone-200/70 bg-white p-5 shadow-sm shadow-stone-200/50 sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-xl font-semibold text-stone-900">Card Growth</h3>
                        <p class="mt-2 text-sm leading-6 text-stone-600">New customer cards added over the last two weeks.</p>
                    </div>
                    <span class="rounded-full bg-[#edf4eb] px-3 py-1 text-xs font-semibold text-[#1f3b2c]">Live</span>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl bg-[#f3efe7] p-4 text-[#3a2a22]">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] opacity-75">Cards added</p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight text-stone-950">{{ number_format($analytics['joins_last_window']) }}</p>
                    </div>
                    <div class="rounded-2xl bg-stone-100 p-4 text-stone-700">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] opacity-75">Daily average</p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight text-stone-950">{{ number_format($analytics['joins_last_window'] / max(1, $trendPoints->count()), 1) }}</p>
                    </div>
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
            </section>
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
                    <div class="mt-4 rounded-xl border border-stone-200 bg-stone-50/80 p-4">
                        <p class="text-sm text-stone-700">
                            Cards issued:
                            <strong>{{ $usageStats['cards_count'] }} / {{ $usageStats['is_subscribed'] ? '∞' : $usageStats['limit'] }}</strong>
                        </p>
                        @if(!$usageStats['is_subscribed'])
                            <p class="mt-1 text-xs text-stone-500">Remaining on free plan: {{ $cardsRemaining }}</p>
                            <div class="mt-3 w-full bg-stone-200 rounded-full h-2">
                                <div class="h-2 rounded-full bg-[#4f7d54] transition-all duration-300" style="width: {{ $usagePercent }}%"></div>
                            </div>
                        @endif
                        @if($usageStats['grandfathered_count'] > 0)
                            <p class="mt-2 text-xs text-stone-500">{{ $usageStats['grandfathered_count'] }} grandfathered card(s) active</p>
                        @endif
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
                    <div class="flex items-center justify-between rounded-xl border border-stone-200 bg-stone-50/80 px-4 py-3">
                        <span class="text-stone-700">Store branding added</span>
                        <span class="font-medium {{ $storesWithLogo === $storesCount && $storesCount > 0 ? 'text-emerald-700' : 'text-amber-700' }}">{{ $storesWithLogo }}/{{ $storesCount }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded-xl border border-stone-200 bg-stone-50/80 px-4 py-3">
                        <span class="text-stone-700">Wallet assets added</span>
                        <span class="font-medium {{ $storesWithWalletAssets === $storesCount && $storesCount > 0 ? 'text-emerald-700' : 'text-amber-700' }}">{{ $storesWithWalletAssets }}/{{ $storesCount }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded-xl border border-stone-200 bg-stone-50/80 px-4 py-3">
                        <span class="text-stone-700">Reward setup complete</span>
                        <span class="font-medium {{ $storesWithRewardRules === $storesCount && $storesCount > 0 ? 'text-emerald-700' : 'text-amber-700' }}">{{ $storesWithRewardRules }}/{{ $storesCount }}</span>
                    </div>
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
                <x-ui.card class="p-4 border border-[#d6a24a]/40 bg-[#fff5df]">
                    <p class="text-sm text-[#7c5a1c]">
                        <strong>Grandfathered Cards:</strong> You have {{ $usageStats['grandfathered_count'] }} active from your previous Pro subscription.
                        You can create {{ $usageStats['limit'] - $usageStats['non_grandfathered_count'] }} more card(s) on free.
                    </p>
                </x-ui.card>
            @elseif($usageStats['cards_count'] >= ($usageStats['limit'] * 0.8))
                <x-ui.card class="p-4 border border-[#d6a24a]/40 bg-[#fff5df]">
                    <p class="text-sm text-[#7c5a1c]">
                        <strong>Almost there:</strong> You’re using {{ $usageStats['cards_count'] }} of {{ $usageStats['limit'] }} free cards.
                        Consider upgrading to keep adding customers.
                    </p>
                </x-ui.card>
            @endif
        @endif
    </div>
</x-merchant-layout>
