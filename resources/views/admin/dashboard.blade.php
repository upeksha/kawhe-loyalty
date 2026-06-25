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
            ['label' => 'New cards', 'value' => number_format($activityTrend['joins_total']), 'tone' => 'bg-brand-50 text-brand-700'],
            ['label' => 'Stamps added', 'value' => number_format($activityTrend['stamps_total']), 'tone' => 'bg-emerald-50 text-emerald-700'],
            ['label' => 'Rewards redeemed', 'value' => number_format($activityTrend['redeems_total']), 'tone' => 'bg-accent-50 text-accent-700'],
        ];

        $growthMetricCards = [
            ['label' => 'Stores added', 'value' => number_format($storeTrend['stores_total']), 'tone' => 'bg-brand-50 text-brand-700'],
            ['label' => 'Issue events', 'value' => number_format($storeTrend['issues_total']), 'tone' => 'bg-stone-100 text-stone-700'],
        ];

        $recentStampItems = $recent_stamps->take(5);
        $recentSupportItems = $recent_support_events->take(5);
    @endphp

    <div class="mx-auto max-w-7xl space-y-8">
        <x-ui.page-hero
            eyebrow="Overview"
            title="Welcome back, {{ auth()->user()->name ?? 'Admin' }}."
            description="This is the platform pulse. The top half focuses on joins, stamps, store growth, and support pressure so we can spot changes quickly without digging through logs first."
        >
            <x-slot name="actions">
                <x-ui.quick-link href="{{ route('admin.support.index') }}" label="Quick Action" title="Open Support Logs" />
                <x-ui.quick-link href="{{ route('admin.support.index', ['event_type' => 'billing_issue']) }}" label="Quick Action" title="Review Billing Issues" hover="accent" />
                <x-ui.quick-link href="{{ route('admin.support.index', ['event_type' => 'wallet_sync', 'issues_only' => 1]) }}" label="Quick Action" title="Check Wallet Sync" hover="emerald" class="sm:col-span-2 xl:col-span-1" />
            </x-slot>
        </x-ui.page-hero>

        <section class="grid gap-4 xl:grid-cols-3">
            @foreach($summaryCards as $card)
                <x-ui.stat-card
                    :label="$card['label']"
                    :value="$card['value']"
                    :caption="$card['caption']"
                    size="lg"
                    tone=""
                    accent="text-stone-950"
                    class="rounded-[26px] border border-stone-200/70 bg-gradient-to-br {{ $card['tone'] }} p-5 shadow-sm shadow-stone-200/70"
                />
            @endforeach
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.75fr_1fr]">
            <x-ui.section-panel class="p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-xl font-semibold text-stone-900">Platform Activity</h3>
                        <p class="mt-2 text-sm leading-6 text-stone-600">Daily joins, stamp updates, and reward redeems over the last 14 days.</p>
                    </div>
                    <x-ui.badge variant="default">Last 14 days</x-ui.badge>
                </div>

                <div class="mt-6 grid gap-3 md:grid-cols-3">
                    @foreach($platformMetricCards as $card)
                        <x-ui.admin-metric :label="$card['label']" :value="$card['value']" :tone="$card['tone']" />
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
            </x-ui.section-panel>

            <div class="space-y-6">
                <x-ui.section-panel class="p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-xl font-semibold text-stone-900">Store Growth</h3>
                            <p class="mt-2 text-sm leading-6 text-stone-600">New stores created versus support pressure.</p>
                        </div>
                        <x-ui.badge variant="info">Live</x-ui.badge>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                        @foreach($growthMetricCards as $card)
                            <x-ui.admin-metric :label="$card['label']" :value="$card['value']" :tone="$card['tone']" />
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
                </x-ui.section-panel>

                <x-ui.section-panel class="p-6">
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
                                            <x-ui.badge :variant="$stamp->type === 'redeem' ? 'accent' : 'success'">{{ ucfirst($stamp->type) }}</x-ui.badge>
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
                                            <x-ui.badge :variant="$event->status === 'failed' ? 'danger' : (in_array($event->status, ['blocked', 'partial'], true) ? 'warning' : 'success')">
                                                {{ ucfirst($event->status) }}
                                            </x-ui.badge>
                                            <p class="mt-1 text-[11px] text-stone-400">{{ $event->created_at->diffForHumans() }}</p>
                                        </div>
                                    </div>
                                @empty
                                    <div class="rounded-2xl border border-dashed border-stone-200 bg-stone-50/70 p-4 text-sm text-stone-500">No recent support events.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </x-ui.section-panel>
            </div>
        </section>

        <x-ui.section-panel class="p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-xl font-semibold text-stone-900">Merchant Issue Diagnostics</h3>
                    <p class="mt-2 text-sm leading-6 text-stone-600">Repeated billing and wallet issues are surfaced here first so support can act before merchants escalate.</p>
                </div>
                <x-ui.button href="{{ route('admin.support.index', ['issues_only' => 1]) }}" variant="secondary" size="sm" class="rounded-full">
                    View all issue logs
                </x-ui.button>
            </div>

            <div class="mt-6 grid gap-3 md:grid-cols-3">
                <x-ui.admin-metric label="Stores to Review" :value="number_format($issueStores)" tone="bg-brand-50 text-brand-700" />
                <x-ui.admin-metric label="Billing Issues" :value="number_format($billingIssues)" tone="bg-accent-50 text-accent-700" />
                <x-ui.admin-metric label="Wallet Issues" :value="number_format($walletIssues)" tone="bg-emerald-50 text-emerald-700" />
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
                                            <x-ui.badge variant="accent">{{ $merchantIssue->billing_issue_count }} billing</x-ui.badge>
                                            <x-ui.badge variant="success">{{ $merchantIssue->wallet_issue_count }} wallet</x-ui.badge>
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
                                        <x-ui.button href="{{ route('admin.support.index', ['store_id' => $merchantIssue->id, 'issues_only' => 1]) }}" variant="ghost" size="sm" class="rounded-full border border-stone-200 px-3 py-1.5 text-brand-700 hover:border-brand-200 hover:bg-brand-50">
                                            Open diagnostics
                                        </x-ui.button>
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
        </x-ui.section-panel>
    </div>
</x-admin-layout>
