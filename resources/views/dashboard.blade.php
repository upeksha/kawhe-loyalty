<x-dashboard-layout :workspace="true">
    @php
        $merchantStores = request()->user()?->stores()->get() ?? collect();
        $storesCount = $merchantStores->count();
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
                            'borderColor' => '#2d5d47',
                        ]],
                    ],
                    'options' => [
                        'layout' => ['padding' => ['top' => 6, 'right' => 4, 'bottom' => 6, 'left' => 4]],
                        'plugins' => [
                            'legend' => ['display' => false],
                            'tooltip' => ['enabled' => false],
                        ],
                        'scales' => [
                            'x' => ['display' => false, 'offset' => false],
                            'y' => ['display' => false, 'grace' => '24%'],
                        ],
                        'elements' => ['line' => ['capBezierPoints' => true, 'borderCapStyle' => 'round']],
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
                            'borderColor' => '#2d5d47',
                        ]],
                    ],
                    'options' => [
                        'layout' => ['padding' => ['top' => 6, 'right' => 4, 'bottom' => 6, 'left' => 4]],
                        'plugins' => [
                            'legend' => ['display' => false],
                            'tooltip' => ['enabled' => false],
                        ],
                        'scales' => [
                            'x' => ['display' => false, 'offset' => false],
                            'y' => ['display' => false, 'grace' => '24%'],
                        ],
                        'elements' => ['line' => ['capBezierPoints' => true, 'borderCapStyle' => 'round']],
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
                            'borderColor' => '#f1a94f',
                        ]],
                    ],
                    'options' => [
                        'layout' => ['padding' => ['top' => 6, 'right' => 4, 'bottom' => 6, 'left' => 4]],
                        'plugins' => [
                            'legend' => ['display' => false],
                            'tooltip' => ['enabled' => false],
                        ],
                        'scales' => [
                            'x' => ['display' => false, 'offset' => false],
                            'y' => ['display' => false, 'grace' => '24%'],
                        ],
                        'elements' => ['line' => ['capBezierPoints' => true, 'borderCapStyle' => 'round']],
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
                        'borderColor' => '#57534e',
                    ],
                    [
                        'label' => 'Stamps',
                        'data' => $stampSeries,
                        'borderWidth' => 3,
                        'pointRadius' => 0,
                        'tension' => 0.42,
                        'fill' => true,
                        'backgroundColor' => 'rgba(79, 125, 84, 0.12)',
                        'borderColor' => '#2d5d47',
                    ],
                    [
                        'label' => 'Redeems',
                        'data' => $redeemSeries,
                        'borderWidth' => 3,
                        'pointRadius' => 0,
                        'tension' => 0.42,
                        'fill' => false,
                        'borderColor' => '#f1a94f',
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
                    'borderColor' => '#2d5d47',
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

    @endphp


    <div class="space-y-6" data-dashboard-design="figma-v1">
        <header class="space-y-2">
            <h1 class="text-3xl font-semibold leading-snug text-stone-900 sm:text-4xl">Welcome back, {{ request()->user()->name ?? 'Merchant' }}</h1>
            <p class="text-base text-stone-600">Here is how your loyalty programme is doing across your stores.</p>
        </header>
        <section aria-label="Loyalty summary" class="grid gap-6 lg:grid-cols-3">
            @foreach($summaryCards as $card)
                <x-ui.card class="dashboard-panel min-w-0 space-y-4 p-6">
                    <h2 class="text-xl font-semibold text-stone-900">{{ $card['label'] }}</h2>
                    <p class="text-4xl font-semibold text-brand-600">{{ $card['value'] }}</p>
                    <div class="relative h-[52px]"><canvas data-chart='@json($card["chart"])' aria-hidden="true"></canvas></div>
                    <p class="text-xs text-stone-600">{{ $loop->first ? 'Active in last 30 days' : 'Last 14 days' }}</p>
                </x-ui.card>
            @endforeach
        </section>
        <section aria-label="Loyalty charts" class="grid gap-6 lg:grid-cols-[3fr_2fr]">
            <x-ui.card class="dashboard-panel min-w-0 space-y-4 p-6">
                <h2 class="text-xl font-semibold text-stone-900">Loyalty Activity</h2>
                <p class="text-sm font-semibold leading-relaxed text-stone-600">New cards {{ number_format($analytics['joins_last_window']) }} · Stamp activity {{ number_format($trendPoints->sum('stamps')) }} · Avg per day {{ number_format($trendPoints->sum('total') / max(1, $trendPoints->count()), 1) }}</p>
                @if($trendPoints->every(fn ($day) => ($day['total'] ?? 0) === 0))
                    <div class="rounded-xl border border-dashed border-stone-200 p-6 text-sm text-stone-600">No recent join or card activity yet. As customers join and start collecting stamps, your activity graph will appear here.</div>
                @else
                    <div class="relative h-64"><canvas data-chart='@json($loyaltyActivityChart)' role="img" aria-label="Loyalty activity chart: daily joins, stamps and redeems over the last 14 days"></canvas></div>
                @endif
            </x-ui.card>
            <x-ui.card class="dashboard-panel min-w-0 space-y-4 p-6">
                <h2 class="text-xl font-semibold text-stone-900">Card Growth</h2>
                <p class="text-sm font-semibold leading-relaxed text-stone-600">Cards added {{ number_format($analytics['joins_last_window']) }} · Daily average {{ number_format($analytics['joins_last_window'] / max(1, $trendPoints->count()), 1) }}</p>
                @if($trendPoints->every(fn ($day) => ($day['joins'] ?? 0) === 0))
                    <div class="rounded-xl border border-dashed border-stone-200 p-6 text-sm text-stone-600">No new customer cards in the last 14 days yet.</div>
                @else
                    <div class="relative h-64"><canvas data-chart='@json($cardGrowthChart)' role="img" aria-label="Card growth chart: new customer cards over the last 14 days"></canvas></div>
                @endif
            </x-ui.card>
        </section>
        <section aria-label="Plan and Wallet readiness" class="grid items-start gap-6 lg:grid-cols-2">
            <x-ui.card class="dashboard-panel p-5 sm:p-6">
                <h3 class="text-xl font-semibold text-stone-900">Your plan</h3>
                @if(isset($usageStats))
                    @php
                        $customersUsed = (int) ($usageStats['primary_program_customers_count'] ?? 0);
                        $customersLimit = $usageStats['customers_per_program_limit'] ?? null;
                        $customersRemaining = $customersLimit ? max(0, $customersLimit - $customersUsed) : null;
                    @endphp
                    <p class="mt-2 text-sm font-medium text-brand-700">{{ $usageStats['is_subscribed'] ? 'Pro plan' : 'Free plan' }}</p>
                    <div class="mt-5">
                        <p class="text-sm text-stone-600">Customers on your primary loyalty card</p>
                        <p class="mt-2 text-2xl font-semibold text-stone-900">
                            {{ number_format($customersUsed) }}
                            <span class="text-sm font-normal text-stone-500">{{ $customersLimit ? 'of '.number_format($customersLimit) : '· no customer limit' }}</span>
                        </p>
                        @if($customersLimit)
                            <progress class="mt-3 h-2 w-full overflow-hidden rounded-full [&::-webkit-progress-bar]:bg-stone-100 [&::-webkit-progress-value]:bg-brand-600 [&::-moz-progress-bar]:bg-brand-600" value="{{ min($customersUsed, $customersLimit) }}" max="{{ $customersLimit }}" aria-label="Customers on your primary loyalty card"></progress>
                            <p class="mt-2 text-sm text-stone-600">{{ $customersRemaining > 0 ? number_format($customersRemaining).' more customers can join this card.' : 'This card is full. Upgrade to welcome more customers.' }}</p>
                        @else
                            <p class="mt-2 text-sm text-stone-600">Keep welcoming new customers with no customer limit.</p>
                        @endif
                    </div>
                    <x-ui.button href="{{ route('billing.index') }}" variant="secondary" class="mt-5 w-full">{{ $usageStats['is_subscribed'] ? 'Manage plan' : 'View plans & billing' }}</x-ui.button>
                @else
                    <p class="mt-2 text-sm text-stone-600">Plan details are temporarily unavailable.</p>
                    <x-ui.button href="{{ route('billing.index') }}" variant="secondary" class="mt-5 w-full">View plans & billing</x-ui.button>
                @endif
            </x-ui.card>

            <x-ui.card class="dashboard-panel p-5 sm:p-6">
                <h3 class="text-xl font-semibold text-stone-900">Wallet cards</h3>
                <p class="mt-2 text-sm text-stone-600">Let customers keep their loyalty card in Apple Wallet or Google Wallet.</p>
                <div class="mt-5">
                    @if($storesCount > 0 && $walletReadyCount === $storesCount)
                        <p class="text-base font-semibold text-brand-700">Branding ready</p>
                        <p class="mt-2 text-sm leading-relaxed text-stone-600">Your store logo, colours and reward details are set up.</p>
                    @elseif($storesCount > 0)
                        <p class="text-base font-semibold text-stone-900">Finish your card setup</p>
                        <p class="mt-2 text-sm leading-relaxed text-stone-600">{{ $storesCount - $walletReadyCount === 1 ? 'One store needs' : ($storesCount - $walletReadyCount).' stores need' }} a logo, colours or reward details before sharing Wallet cards.</p>
                    @else
                        <p class="text-base font-semibold text-stone-900">Set up your first store</p>
                        <p class="mt-2 text-sm leading-relaxed text-stone-600">Add your store and loyalty card details to get started.</p>
                    @endif
                </div>
                <x-ui.button href="{{ route('merchant.stores.index') }}" variant="secondary" class="mt-5 w-full">{{ $storesCount > 0 && $walletReadyCount === $storesCount ? 'Review card setup' : 'Complete card setup' }}</x-ui.button>
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
</x-dashboard-layout>
