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
        $trendMax = max(1, $trendPoints->max('total') ?? 0);
        $joinMax = max(1, $trendPoints->max('joins') ?? 0);

        $buildChart = function ($values, $width, $height) {
            $values = collect($values)->map(fn ($value) => (int) $value)->values();
            $count = $values->count();

            if ($count === 0) {
                return ['line' => '', 'area' => ''];
            }

            $max = max(1, $values->max());
            $stepX = $count > 1 ? $width / ($count - 1) : $width;
            $baseline = $height;
            $points = [];

            foreach ($values as $index => $value) {
                $x = round($index * $stepX, 2);
                $y = round($height - (($value / $max) * ($height - 18)) - 9, 2);
                $points[] = [$x, $y];
            }

            $line = collect($points)
                ->map(fn ($point, $index) => ($index === 0 ? 'M' : 'L') . $point[0] . ' ' . $point[1])
                ->implode(' ');

            return [
                'line' => $line,
                'area' => $line . ' L ' . $points[$count - 1][0] . ' ' . $baseline . ' L 0 ' . $baseline . ' Z',
            ];
        };

        $platformChart = $buildChart($trendPoints->pluck('joins')->all(), 760, 225);
        $stampsChart = $buildChart($trendPoints->pluck('stamps')->all(), 760, 225);
        $redeemsChart = $buildChart($trendPoints->pluck('redeems')->all(), 760, 225);
        $joinOnlyChart = $buildChart($trendPoints->pluck('joins')->all(), 390, 225);

        $joinCardChart = $buildChart($trendPoints->pluck('joins')->all(), 180, 58);
        $earnedCardChart = $buildChart($trendPoints->map(fn ($day) => ($day['stamps'] ?? 0) + ($day['redeems'] ?? 0))->all(), 180, 58);
        $redeemedCardChart = $buildChart($trendPoints->pluck('redeems')->all(), 180, 58);

        $summaryCards = [
            [
                'label' => 'Active customers',
                'value' => number_format($analytics['active_customers']),
                'caption' => 'Joined or used their card in the last 30 days',
                'tone' => 'bg-[#eef1ff]',
                'accent' => 'text-[#5b6cff]',
                'spark' => $joinCardChart['line'],
            ],
            [
                'label' => 'Rewards earned',
                'value' => number_format($analytics['rewards_earned_last_window']),
                'caption' => 'Completed reward cycles in the last 30 days',
                'tone' => 'bg-[#eefcf6]',
                'accent' => 'text-[#31b67a]',
                'spark' => $earnedCardChart['line'],
            ],
            [
                'label' => 'Rewards redeemed',
                'value' => number_format($analytics['rewards_redeemed_last_window']),
                'caption' => 'Redeemed across your stores in the last 30 days',
                'tone' => 'bg-[#f3fbf6]',
                'accent' => 'text-[#39b980]',
                'spark' => $redeemedCardChart['line'],
            ],
        ];

        $miniActivity = $trendPoints->take(-5);
    @endphp

    <div class="space-y-6 sm:space-y-8">
        <section class="rounded-[30px] border border-stone-200/70 bg-white p-5 shadow-sm shadow-stone-200/50 sm:p-8">
            <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-2xl">
                    <p class="text-sm font-semibold uppercase tracking-[0.24em] text-brand-600">Dashboard</p>
                    <h2 class="mt-3 text-3xl font-semibold tracking-tight text-stone-900 sm:text-4xl">
                        Welcome back, {{ request()->user()->name ?? 'Merchant' }}.
                    </h2>
                    <p class="mt-3 max-w-xl text-base leading-7 text-stone-600">
                        Here’s the clearest read on customer growth, reward usage, and store readiness across your loyalty program.
                    </p>
                </div>

                <div class="w-full max-w-[34rem]">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-400">Quick Actions</p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-3">
                        <x-ui.button href="{{ route('merchant.scanner') }}" variant="primary" size="sm" class="!justify-start !rounded-2xl !border-0 !bg-[#eef1ff] !px-4 !py-4 !text-[#5b6cff] !shadow-none hover:!bg-[#e5e9ff]">
                            Open Scanner
                        </x-ui.button>
                        <x-ui.button href="{{ route('merchant.stores.index') }}" variant="secondary" size="sm" class="!justify-start !rounded-2xl !border-0 !bg-stone-100 !px-4 !py-4 !text-stone-700 !shadow-none hover:!bg-stone-200">
                            Store QR
                        </x-ui.button>
                        <x-ui.button href="{{ route('merchant.customers.index') }}" variant="secondary" size="sm" class="!justify-start !rounded-2xl !border-0 !bg-stone-100 !px-4 !py-4 !text-stone-700 !shadow-none hover:!bg-stone-200">
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
                        <svg viewBox="0 0 180 58" class="mt-2 h-16 w-28 shrink-0" fill="none" preserveAspectRatio="none" aria-hidden="true">
                            <path d="{{ $card['spark'] }}" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="{{ $card['accent'] }}" />
                        </svg>
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
                    <div class="rounded-2xl bg-[#eaf1ff] p-4 text-[#5b6cff]">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] opacity-75">New cards</p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight text-stone-950">{{ number_format($analytics['joins_last_window']) }}</p>
                    </div>
                    <div class="rounded-2xl bg-[#ecfbf3] p-4 text-[#31b67a]">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] opacity-75">Stamp activity</p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight text-stone-950">{{ number_format($trendPoints->sum('stamps')) }}</p>
                    </div>
                    <div class="rounded-2xl bg-[#fff8df] p-4 text-[#d9a227]">
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
                        <svg viewBox="0 0 760 245" class="h-[20rem] w-full" fill="none" preserveAspectRatio="none" aria-hidden="true">
                            @foreach(range(1, 4) as $line)
                                <line x1="0" y1="{{ $line * 49 }}" x2="760" y2="{{ $line * 49 }}" stroke="#e7e5e4" stroke-dasharray="4 6" />
                            @endforeach

                            <path d="{{ $stampsChart['area'] }}" fill="#e7faf0" fill-opacity="0.95" />
                            <path d="{{ $platformChart['area'] }}" fill="#eef1ff" fill-opacity="0.9" />
                            <path d="{{ $redeemsChart['line'] }}" stroke="#d9a227" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="{{ $stampsChart['line'] }}" stroke="#31b67a" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="{{ $platformChart['line'] }}" stroke="#5b6cff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>

                        <div class="mt-4 grid grid-cols-7 gap-2 text-[11px] font-medium text-stone-400 sm:grid-cols-14">
                            @foreach($trendPoints as $point)
                                <div class="text-center">{{ $point['label'] }}</div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-4 text-xs text-stone-600">
                        <span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-brand-500"></span>Joins</span>
                        <span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>Stamps</span>
                        <span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>Redeems</span>
                    </div>
                @endif
            </section>

            <section class="rounded-[30px] border border-stone-200/70 bg-white p-5 shadow-sm shadow-stone-200/50 sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-xl font-semibold text-stone-900">Card Growth</h3>
                        <p class="mt-2 text-sm leading-6 text-stone-600">New customer cards added over the last two weeks.</p>
                    </div>
                    <span class="rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700">Live</span>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl bg-[#eaf1ff] p-4 text-[#5b6cff]">
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
                        <svg viewBox="0 0 390 245" class="h-64 w-full" fill="none" preserveAspectRatio="none" aria-hidden="true">
                            @foreach(range(1, 4) as $line)
                                <line x1="0" y1="{{ $line * 49 }}" x2="390" y2="{{ $line * 49 }}" stroke="#e7e5e4" stroke-dasharray="4 6" />
                            @endforeach

                                <path d="{{ $joinOnlyChart['area'] }}" fill="#eef1ff" fill-opacity="0.95" />
                                <path d="{{ $joinOnlyChart['line'] }}" stroke="#5b6cff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
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
                                <div class="bg-brand-600 h-2 rounded-full transition-all duration-300" style="width: {{ $usagePercent }}%"></div>
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
    </div>
</x-merchant-layout>
