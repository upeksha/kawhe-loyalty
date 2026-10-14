<x-dashboard-layout>
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

        $miniActivity = $trendPoints->take(-5);
    @endphp


    <div class="space-y-6" data-dashboard-design="figma-v1">
        <header class="space-y-2">
            <h1 class="text-3xl font-semibold leading-snug text-stone-900 sm:text-4xl">Welcome back, {{ request()->user()->name ?? 'Merchant' }}</h1>
            <p class="text-base text-stone-600">Here is how your loyalty programme is doing across your stores.</p>
        </header>
        <nav aria-label="Merchant navigation" class="grid grid-cols-2 gap-2 sm:grid-cols-5 xl:grid-cols-10">
            @foreach([
                ['Overview', route('merchant.dashboard'), true],
                ['Cards', route('merchant.programs.index'), false],
                ['Customers', route('merchant.customers.index'), false],
                ['Team', route('merchant.staff.index'), false],
                ['Activity', route('merchant.support.index'), false],
                ['Billing', route('billing.index'), false],
                ['Settings', route('profile.edit'), false],
                ['Stores', route('merchant.stores.index'), false],
                ['Support', route('merchant.support.index'), false],
            ] as [$label, $url, $active])
                <a href="{{ $url }}" @if($active) aria-current="page" @endif class="dashboard-button flex items-center justify-center rounded-lg border border-stone-200 px-3 text-sm font-semibold text-brand-700 {{ $active ? 'bg-brand-50' : 'bg-white hover:bg-brand-50' }}">{{ $label }}</a>
            @endforeach
            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="dashboard-button w-full rounded-lg bg-white px-3 text-sm font-semibold text-brand-700 hover:bg-brand-50">Sign out</button></form>
        </nav>
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
            @if(isset($usageStats))
                <x-ui.card class="dashboard-panel p-5 sm:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-xl font-semibold text-stone-900">Plan Health</h3>
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
                        $cardsLimit = $usageStats['programs_per_store_limit'] ?? null;
                        $customersUsed = (int) ($usageStats['primary_program_customers_count'] ?? 0);
                        $customersLimit = $usageStats['customers_per_program_limit'] ?? null;
                    @endphp
                    <div class="mt-4 space-y-3">
                        <div class="rounded-xl border border-stone-200 bg-white p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Stores</p>
                            <p class="mt-2 text-lg font-semibold text-stone-900">
                                {{ $storesUsed }}@if($storesLimit)<span class="text-sm font-medium text-stone-500"> / {{ $storesLimit }}</span>@endif
                            </p>
                            <p class="mt-1 text-xs text-stone-600">{{ ($usageStats['can_create_store'] ?? false) ? 'Room for another store' : 'Store limit reached' }}</p>
                            <x-ui.usage-meter class="mt-3" :used="$storesUsed" :limit="$storesLimit" />
                        </div>

                        <div class="rounded-xl border border-stone-200 bg-white p-4">
                            <x-ui.store-cards-usage
                                :stores="$usageStats['stores_card_usage'] ?? []"
                                :limit="$cardsLimit"
                                :is-subscribed="(bool) ($usageStats['is_subscribed'] ?? false)"
                                :can-create-program="(bool) ($usageStats['can_create_program'] ?? false)"
                                compact
                            />
                        </div>

                        <div class="rounded-xl border border-stone-200 bg-white p-4">
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
                    <h3 class="text-xl font-semibold text-stone-900">Plan Health</h3>
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

            <x-ui.card class="dashboard-panel p-5 sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-xl font-semibold text-stone-900">Wallet Readiness</h3>
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
                <x-ui.button href="{{ route('merchant.stores.index') }}" variant="secondary" class="dashboard-button mt-6 w-full">Review store settings</x-ui.button>
            </x-ui.card>
        </section>
        <x-ui.card id="recent-activity" class="dashboard-panel scroll-mt-6 p-6">
            <h2 class="text-xl font-semibold text-stone-900">Recent Activity</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm sm:text-base">
                    <caption class="sr-only">Recent daily customer activity across your stores</caption>
                    <thead class="text-sm text-stone-600"><tr>@foreach(['Date', 'New cards', 'Stamps', 'Redeems', 'Total events'] as $label)<th scope="col" class="whitespace-nowrap px-3 py-3 font-semibold first:pl-0">{{ $label }}</th>@endforeach</tr></thead>
                    <tbody>@forelse($miniActivity->reverse() as $day)
                        <tr class="text-stone-900"><th scope="row" class="whitespace-nowrap py-3 pr-3 font-normal">{{ $day['label'] }}</th>@foreach(['joins', 'stamps', 'redeems', 'total'] as $key)<td class="px-3 py-3">{{ number_format($day[$key]) }}</td>@endforeach</tr>
                    @empty<tr><td colspan="5" class="py-4 text-stone-600">No recent activity yet.</td></tr>@endforelse</tbody>
                </table>
            </div>
        </x-ui.card>
        <div class="grid max-w-[400px] gap-6">
            <x-ui.button href="{{ route('merchant.scanner') }}" class="dashboard-button">Open scanner</x-ui.button>
            <x-ui.button href="{{ route('merchant.stores.index') }}" variant="secondary" class="dashboard-button">Store QR</x-ui.button>
            <x-ui.button href="{{ route('merchant.customers.index') }}" variant="secondary" class="dashboard-button">Customers</x-ui.button>
        </div>
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
