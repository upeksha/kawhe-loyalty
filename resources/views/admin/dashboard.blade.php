<x-admin-layout>
    <x-slot name="header">
        Admin Dashboard
    </x-slot>

    @php
        $billingIssues = collect($merchant_issue_diagnostics)->sum('billing_issue_count');
        $walletIssues = collect($merchant_issue_diagnostics)->sum('wallet_issue_count');
        $issueStores = collect($merchant_issue_diagnostics)->count();

        $summaryCards = [
            [
                'label' => 'Total Users',
                'value' => number_format($stats['total_users']),
                'caption' => 'Merchant and admin accounts across the platform.',
                'tone' => 'from-brand-50 via-white to-white text-brand-700',
            ],
            [
                'label' => 'Total Stores',
                'value' => number_format($stats['total_stores']),
                'caption' => 'Loyalty programs currently created in the system.',
                'tone' => 'from-violet-50 via-white to-white text-violet-700',
            ],
            [
                'label' => 'Rewards Pressure',
                'value' => number_format($billingIssues + $walletIssues),
                'caption' => 'Billing and wallet issues requiring follow-up.',
                'tone' => 'from-emerald-50 via-white to-white text-emerald-700',
            ],
        ];

        $platformMetricCards = [
            [
                'label' => 'New cards',
                'value' => number_format($activityTrend['joins_total']),
                'tone' => 'bg-brand-50 text-brand-700',
            ],
            [
                'label' => 'Stamps added',
                'value' => number_format($activityTrend['stamps_total']),
                'tone' => 'bg-emerald-50 text-emerald-700',
            ],
            [
                'label' => 'Rewards redeemed',
                'value' => number_format($activityTrend['redeems_total']),
                'tone' => 'bg-accent-50 text-accent-700',
            ],
        ];

        $growthMetricCards = [
            [
                'label' => 'Stores added',
                'value' => number_format($storeTrend['stores_total']),
                'tone' => 'bg-brand-50 text-brand-700',
            ],
            [
                'label' => 'Issue events',
                'value' => number_format($storeTrend['issues_total']),
                'tone' => 'bg-stone-100 text-stone-700',
            ],
        ];

        $recentStampItems = $recent_stamps->take(5);
        $recentSupportItems = $recent_support_events->take(5);
    @endphp

    <div class="mx-auto max-w-7xl space-y-8">
        <section class="rounded-[28px] border border-stone-200/80 bg-white p-6 shadow-sm shadow-stone-200/60 sm:p-8">
            <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-2xl">
                    <p class="text-sm font-semibold uppercase tracking-[0.24em] text-brand-600">Overview</p>
                    <h2 class="mt-3 text-3xl font-semibold tracking-tight text-stone-900 sm:text-4xl">
                        Welcome back, {{ auth()->user()->name ?? 'Admin' }}.
                    </h2>
                    <p class="mt-3 max-w-xl text-base leading-7 text-stone-600">
                        This is the platform pulse. The top half focuses on joins, stamps, store growth, and support pressure so we can spot changes quickly without digging through logs first.
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-3 xl:w-[34rem]">
                    <a href="{{ route('admin.support.index') }}" class="rounded-2xl border border-stone-200 bg-stone-50 px-4 py-4 transition hover:border-brand-200 hover:bg-brand-50/60">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Quick Action</p>
                        <p class="mt-2 text-sm font-semibold text-stone-900">Open Support Logs</p>
                    </a>
                    <a href="{{ route('admin.support.index', ['event_type' => 'billing_issue']) }}" class="rounded-2xl border border-stone-200 bg-stone-50 px-4 py-4 transition hover:border-accent-200 hover:bg-accent-50/50">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Quick Action</p>
                        <p class="mt-2 text-sm font-semibold text-stone-900">Review Billing Issues</p>
                    </a>
                    <a href="{{ route('admin.support.index', ['event_type' => 'wallet_sync', 'issues_only' => 1]) }}" class="rounded-2xl border border-stone-200 bg-stone-50 px-4 py-4 transition hover:border-emerald-200 hover:bg-emerald-50/50">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Quick Action</p>
                        <p class="mt-2 text-sm font-semibold text-stone-900">Check Wallet Sync</p>
                    </a>
                </div>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-3">
            @foreach($summaryCards as $card)
                <article class="rounded-[26px] border border-stone-200/70 bg-gradient-to-br {{ $card['tone'] }} p-5 shadow-sm shadow-stone-200/70">
                    <p class="text-sm font-medium text-stone-500">{{ $card['label'] }}</p>
                    <p class="mt-4 text-4xl font-semibold tracking-tight text-stone-950">{{ $card['value'] }}</p>
                    <p class="mt-3 max-w-[16rem] text-sm leading-6 text-stone-600">{{ $card['caption'] }}</p>
                </article>
            @endforeach
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.75fr_1fr]">
            <section class="rounded-[28px] border border-stone-200/80 bg-white p-6 shadow-sm shadow-stone-200/60">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-xl font-semibold text-stone-900">Platform Activity</h3>
                        <p class="mt-2 text-sm leading-6 text-stone-600">Daily joins, stamp updates, and reward redeems over the last 14 days.</p>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold text-stone-600">Last 14 days</span>
                </div>

                <div class="mt-6 grid gap-3 md:grid-cols-3">
                    @foreach($platformMetricCards as $card)
                        <div class="rounded-2xl {{ $card['tone'] }} p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] opacity-75">{{ $card['label'] }}</p>
                            <p class="mt-3 text-3xl font-semibold tracking-tight text-stone-950">{{ $card['value'] }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 rounded-[24px] border border-stone-200 bg-stone-50/70 p-4 sm:p-5">
                    <svg viewBox="0 0 780 260" class="h-[20rem] w-full" fill="none" preserveAspectRatio="none" aria-hidden="true">
                        @foreach(range(1, 4) as $line)
                            <line x1="0" y1="{{ $line * 52 }}" x2="780" y2="{{ $line * 52 }}" stroke="#e7e5e4" stroke-dasharray="4 6" />
                        @endforeach

                        <path d="{{ $activityTrend['stamps_chart']['area'] }}" fill="#d1fae5" fill-opacity="0.5" />
                        <path d="{{ $activityTrend['joins_chart']['area'] }}" fill="#dbeafe" fill-opacity="0.65" />
                        <path d="{{ $activityTrend['redeems_chart']['line'] }}" stroke="#f59e0b" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="{{ $activityTrend['stamps_chart']['line'] }}" stroke="#10b981" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="{{ $activityTrend['joins_chart']['line'] }}" stroke="#4f46e5" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>

                    <div class="mt-4 grid grid-cols-7 gap-2 text-[11px] font-medium text-stone-400 sm:grid-cols-14">
                        @foreach($activityTrend['points'] as $point)
                            <div class="text-center">{{ $point['label'] }}</div>
                        @endforeach
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-3 text-sm text-stone-600">
                    <span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-indigo-600"></span> New cards</span>
                    <span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span> Stamps added</span>
                    <span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span> Rewards redeemed</span>
                </div>
            </section>

            <div class="space-y-6">
                <section class="rounded-[28px] border border-stone-200/80 bg-white p-6 shadow-sm shadow-stone-200/60">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-xl font-semibold text-stone-900">Store Growth</h3>
                            <p class="mt-2 text-sm leading-6 text-stone-600">New stores created versus support pressure.</p>
                        </div>
                        <span class="rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700">Live</span>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                        @foreach($growthMetricCards as $card)
                            <div class="rounded-2xl {{ $card['tone'] }} p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] opacity-75">{{ $card['label'] }}</p>
                                <p class="mt-3 text-3xl font-semibold tracking-tight text-stone-950">{{ $card['value'] }}</p>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-6 rounded-[24px] border border-stone-200 bg-stone-50/70 p-4">
                        <svg viewBox="0 0 420 250" class="h-64 w-full" fill="none" preserveAspectRatio="none" aria-hidden="true">
                            @foreach(range(1, 4) as $line)
                                <line x1="0" y1="{{ $line * 50 }}" x2="420" y2="{{ $line * 50 }}" stroke="#e7e5e4" stroke-dasharray="4 6" />
                            @endforeach

                            <path d="{{ $storeTrend['stores_chart']['area'] }}" fill="#dbeafe" fill-opacity="0.7" />
                            <path d="{{ $storeTrend['issues_chart']['line'] }}" stroke="#78716c" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="{{ $storeTrend['stores_chart']['line'] }}" stroke="#4f46e5" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-3 text-sm text-stone-600">
                        <span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-indigo-600"></span> Stores added</span>
                        <span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-stone-500"></span> Issue events</span>
                    </div>
                </section>

                <section class="rounded-[28px] border border-stone-200/80 bg-white p-6 shadow-sm shadow-stone-200/60">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-xl font-semibold text-stone-900">Recent Activity</h3>
                            <p class="mt-2 text-sm leading-6 text-stone-600">Compact view of the latest customer and support movement.</p>
                        </div>
                        <a href="{{ route('admin.support.index') }}" class="text-sm font-medium text-brand-700 hover:text-brand-800">View all</a>
                    </div>

                    <div class="mt-5 space-y-5">
                        <div>
                            <div class="mb-3 flex items-center justify-between">
                                <h4 class="text-sm font-semibold uppercase tracking-[0.16em] text-stone-400">Stamp activity</h4>
                                <span class="text-xs text-stone-500">{{ $recentStampItems->count() }} latest</span>
                            </div>
                            <div class="space-y-2.5">
                                @forelse($recentStampItems as $stamp)
                                    <div class="flex items-center justify-between gap-3 rounded-2xl border border-stone-200 bg-stone-50/70 px-3 py-3">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-stone-900">{{ $stamp->loyaltyAccount->customer->email ?? 'Unknown customer' }}</p>
                                            <p class="truncate text-xs text-stone-500">{{ $stamp->store->name }}</p>
                                        </div>
                                        <div class="text-right">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $stamp->type === 'redeem' ? 'bg-accent-50 text-accent-700' : 'bg-emerald-50 text-emerald-700' }}">{{ ucfirst($stamp->type) }}</span>
                                            <p class="mt-1 text-[11px] text-stone-400">{{ $stamp->created_at->diffForHumans() }}</p>
                                        </div>
                                    </div>
                                @empty
                                    <div class="rounded-2xl border border-dashed border-stone-200 bg-stone-50/70 p-4 text-sm text-stone-500">No recent stamp activity.</div>
                                @endforelse
                            </div>
                        </div>

                        <div>
                            <div class="mb-3 flex items-center justify-between">
                                <h4 class="text-sm font-semibold uppercase tracking-[0.16em] text-stone-400">Support events</h4>
                                <span class="text-xs text-stone-500">{{ $recentSupportItems->count() }} latest</span>
                            </div>
                            <div class="space-y-2.5">
                                @forelse($recentSupportItems as $event)
                                    <div class="flex items-start justify-between gap-3 rounded-2xl border border-stone-200 bg-white px-3 py-3">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-stone-900">{{ str($event->event_type)->replace('_', ' ')->title() }}</p>
                                            <p class="truncate text-xs text-stone-500">{{ $event->store->name ?? 'System' }}</p>
                                        </div>
                                        <div class="text-right">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $event->status === 'failed' ? 'bg-red-50 text-red-700' : ($event->status === 'blocked' || $event->status === 'partial' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700') }}">{{ ucfirst($event->status) }}</span>
                                            <p class="mt-1 text-[11px] text-stone-400">{{ $event->created_at->diffForHumans() }}</p>
                                        </div>
                                    </div>
                                @empty
                                    <div class="rounded-2xl border border-dashed border-stone-200 bg-stone-50/70 p-4 text-sm text-stone-500">No recent support events.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </section>

        <section class="rounded-[28px] border border-stone-200/80 bg-white p-6 shadow-sm shadow-stone-200/60">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-xl font-semibold text-stone-900">Merchant Issue Diagnostics</h3>
                    <p class="mt-2 text-sm leading-6 text-stone-600">Repeated billing and wallet issues are surfaced here first so support can act before merchants escalate.</p>
                </div>
                <a href="{{ route('admin.support.index', ['issues_only' => 1]) }}" class="inline-flex items-center rounded-full border border-stone-200 bg-stone-50 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-brand-200 hover:text-brand-700">
                    View all issue logs
                </a>
            </div>

            <div class="mt-6 grid gap-3 md:grid-cols-3">
                <div class="rounded-2xl bg-brand-50 p-4 text-brand-700">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] opacity-70">Stores to Review</p>
                    <p class="mt-3 text-3xl font-semibold tracking-tight text-stone-950">{{ number_format($issueStores) }}</p>
                </div>
                <div class="rounded-2xl bg-accent-50 p-4 text-accent-700">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] opacity-70">Billing Issues</p>
                    <p class="mt-3 text-3xl font-semibold tracking-tight text-stone-950">{{ number_format($billingIssues) }}</p>
                </div>
                <div class="rounded-2xl bg-emerald-50 p-4 text-emerald-700">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] opacity-70">Wallet Issues</p>
                    <p class="mt-3 text-3xl font-semibold tracking-tight text-stone-950">{{ number_format($walletIssues) }}</p>
                </div>
            </div>

            <div class="mt-6 overflow-hidden rounded-3xl border border-stone-200">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-stone-200">
                        <thead class="bg-stone-50">
                            <tr>
                                <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Store</th>
                                <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Owner</th>
                                <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Issue Mix</th>
                                <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Recommended next step</th>
                                <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-200 bg-white">
                            @forelse($merchant_issue_diagnostics as $merchantIssue)
                                <tr class="align-top">
                                    <td class="px-5 py-4">
                                        <div class="font-semibold text-stone-900">{{ $merchantIssue->name }}</div>
                                        <div class="mt-1 text-xs text-stone-500">Store #{{ $merchantIssue->id }}</div>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-stone-600">{{ $merchantIssue->email }}</td>
                                    <td class="px-5 py-4">
                                        <div class="flex flex-wrap gap-2">
                                            <span class="inline-flex items-center rounded-full bg-accent-50 px-3 py-1 text-xs font-semibold text-accent-700">{{ $merchantIssue->billing_issue_count }} billing</span>
                                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">{{ $merchantIssue->wallet_issue_count }} wallet</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-sm leading-6 text-stone-600">
                                        @if($merchantIssue->billing_issue_count > 1)
                                            Review Stripe customer health and subscription sync first.
                                        @elseif($merchantIssue->wallet_issue_count > 1)
                                            Review wallet registrations and recent sync outcomes next.
                                        @else
                                            Check the latest support log entries to decide the right follow-up.
                                        @endif
                                    </td>
                                    <td class="px-5 py-4">
                                        <a href="{{ route('admin.support.index', ['store_id' => $merchantIssue->id, 'issues_only' => 1]) }}" class="inline-flex items-center rounded-full border border-stone-200 px-3 py-1.5 text-sm font-medium text-brand-700 transition hover:border-brand-200 hover:bg-brand-50">
                                            Open diagnostics
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-10 text-center text-sm text-stone-500">No repeated merchant billing or wallet issues in the last 14 days.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</x-admin-layout>
