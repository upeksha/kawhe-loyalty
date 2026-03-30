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
                'caption' => 'All merchant and admin accounts in the system.',
                'tone' => 'from-brand-50 via-brand-50/70 to-white text-brand-700',
                'spark' => 'M0 58 C22 52, 36 42, 58 34 S96 18, 120 24 S160 46, 188 16',
            ],
            [
                'label' => 'Total Stores',
                'value' => number_format($stats['total_stores']),
                'caption' => 'Published and in-progress loyalty programs.',
                'tone' => 'from-violet-50 via-violet-50/80 to-white text-violet-700',
                'spark' => 'M0 52 C18 30, 40 18, 64 26 S108 54, 130 48 S168 24, 188 12',
            ],
            [
                'label' => 'Stamps Today',
                'value' => number_format($stats['total_stamps_today']),
                'caption' => 'Live loyalty activity recorded today.',
                'tone' => 'from-emerald-50 via-emerald-50/80 to-white text-emerald-700',
                'spark' => 'M0 50 C16 54, 30 44, 52 26 S90 8, 114 28 S152 62, 188 20',
            ],
            [
                'label' => 'Support Events (7 days)',
                'value' => number_format($stats['support_events_last_7_days']),
                'caption' => 'Wallet, verification, billing, and manual support actions.',
                'tone' => 'from-amber-50 via-amber-50/80 to-white text-amber-700',
                'spark' => 'M0 30 C24 26, 42 36, 66 18 S110 20, 136 40 S164 64, 188 28',
            ],
        ];

        $issueSummaryCards = [
            [
                'label' => 'Stores to Review',
                'value' => number_format($issueStores),
                'caption' => 'Merchants with repeated wallet or billing issues in the last 14 days.',
                'tone' => 'bg-brand-50 text-brand-700',
            ],
            [
                'label' => 'Billing Issues',
                'value' => number_format($billingIssues),
                'caption' => 'Failed checkout, sync, or portal events that need follow-up.',
                'tone' => 'bg-accent-50 text-accent-700',
            ],
            [
                'label' => 'Wallet Issues',
                'value' => number_format($walletIssues),
                'caption' => 'Partial or failed pass sync activity across Apple and Google Wallet.',
                'tone' => 'bg-emerald-50 text-emerald-700',
            ],
        ];
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
                        This view keeps the platform health, merchant pressure points, and recent support activity in one place so we can spot problems before merchants feel them.
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

        <section class="grid gap-4 xl:grid-cols-4">
            @foreach($summaryCards as $card)
                <article class="relative overflow-hidden rounded-[26px] border border-stone-200/70 bg-gradient-to-br {{ $card['tone'] }} p-5 shadow-sm shadow-stone-200/70">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-stone-500">{{ $card['label'] }}</p>
                            <p class="mt-4 text-4xl font-semibold tracking-tight text-stone-950">{{ $card['value'] }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/70 bg-white/80 px-2.5 py-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-500">
                            Live
                        </div>
                    </div>
                    <p class="mt-3 max-w-[14rem] text-sm leading-6 text-stone-600">{{ $card['caption'] }}</p>
                    <svg class="mt-6 h-16 w-full" viewBox="0 0 188 64" fill="none" preserveAspectRatio="none" aria-hidden="true">
                        <path d="{{ $card['spark'] }}" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" class="opacity-90"/>
                    </svg>
                </article>
            @endforeach
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.65fr_1fr]">
            <div class="rounded-[28px] border border-stone-200/80 bg-white p-6 shadow-sm shadow-stone-200/60">
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
                    @foreach($issueSummaryCards as $card)
                        <div class="rounded-2xl {{ $card['tone'] }} p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] opacity-70">{{ $card['label'] }}</p>
                            <p class="mt-3 text-3xl font-semibold tracking-tight text-stone-950">{{ $card['value'] }}</p>
                            <p class="mt-2 text-sm leading-6 text-stone-600">{{ $card['caption'] }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 overflow-hidden rounded-3xl border border-stone-200">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-stone-200">
                            <thead class="bg-stone-50">
                                <tr>
                                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Store</th>
                                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Owner</th>
                                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Pressure</th>
                                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Next Step</th>
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
                                                <span class="inline-flex items-center rounded-full bg-accent-50 px-3 py-1 text-xs font-semibold text-accent-700">
                                                    {{ $merchantIssue->billing_issue_count }} billing
                                                </span>
                                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                                    {{ $merchantIssue->wallet_issue_count }} wallet
                                                </span>
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
            </div>

            <div class="space-y-6">
                <section class="rounded-[28px] border border-stone-200/80 bg-white p-6 shadow-sm shadow-stone-200/60">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-xl font-semibold text-stone-900">Recent Stores</h3>
                            <p class="mt-2 text-sm leading-6 text-stone-600">New stores and reward setups created most recently.</p>
                        </div>
                        <span class="rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700">{{ $recent_stores->count() }} shown</span>
                    </div>
                    <div class="mt-5 space-y-3">
                        @forelse($recent_stores as $store)
                            <div class="rounded-2xl border border-stone-200 bg-stone-50/70 p-4">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="font-semibold text-stone-900">{{ $store->name }}</p>
                                        <p class="mt-1 text-sm text-stone-600">{{ $store->user->email }}</p>
                                    </div>
                                    <span class="rounded-full bg-white px-3 py-1 text-xs font-medium text-stone-500">{{ $store->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="mt-3 text-sm text-stone-600">Reward: {{ $store->reward_title }}</p>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-stone-200 bg-stone-50/70 p-6 text-sm text-stone-500">
                                No stores yet.
                            </div>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-[28px] border border-stone-200/80 bg-white p-6 shadow-sm shadow-stone-200/60">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-xl font-semibold text-stone-900">Recent Stamp Activity</h3>
                            <p class="mt-2 text-sm leading-6 text-stone-600">Latest customer updates across stores.</p>
                        </div>
                        <span class="rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold text-stone-600">{{ $recent_stamps->count() }} events</span>
                    </div>
                    <div class="mt-5 space-y-3">
                        @forelse($recent_stamps as $stamp)
                            <div class="rounded-2xl border border-stone-200 bg-white p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-stone-900">{{ $stamp->loyaltyAccount->customer->email ?? 'N/A' }}</p>
                                        <p class="mt-1 text-sm text-stone-600">{{ $stamp->store->name }}</p>
                                    </div>
                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $stamp->event_type === 'stamp' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                        {{ ucfirst($stamp->event_type) }}
                                    </span>
                                </div>
                                <p class="mt-3 text-xs uppercase tracking-[0.18em] text-stone-400">{{ $stamp->created_at->diffForHumans() }}</p>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-stone-200 bg-stone-50/70 p-6 text-sm text-stone-500">
                                No activity yet.
                            </div>
                        @endforelse
                    </div>
                </section>
            </div>
        </section>

        <section class="rounded-[28px] border border-stone-200/80 bg-white p-6 shadow-sm shadow-stone-200/60">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-xl font-semibold text-stone-900">Recent Support Events</h3>
                    <p class="mt-2 text-sm leading-6 text-stone-600">A live feed of the latest wallet, verification, billing, and manual support actions.</p>
                </div>
                <a href="{{ route('admin.support.index') }}" class="inline-flex items-center rounded-full border border-stone-200 bg-stone-50 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-brand-200 hover:text-brand-700">
                    Open support logs
                </a>
            </div>

            <div class="mt-6 overflow-hidden rounded-3xl border border-stone-200">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-stone-200">
                        <thead class="bg-stone-50">
                            <tr>
                                <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Event</th>
                                <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Store</th>
                                <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Actor</th>
                                <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Status</th>
                                <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-200 bg-white">
                            @forelse($recent_support_events as $event)
                                <tr class="align-top">
                                    <td class="px-5 py-4">
                                        <div class="font-semibold text-stone-900">{{ str($event->event_type)->replace('_', ' ')->title() }}</div>
                                        <div class="mt-1 text-sm leading-6 text-stone-500">{{ $event->message }}</div>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-stone-600">{{ $event->store->name ?? 'System' }}</td>
                                    <td class="px-5 py-4 text-sm text-stone-600">{{ $event->actor->email ?? ($event->source ?? 'system') }}</td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $event->status === 'failed' ? 'bg-red-50 text-red-700' : ($event->status === 'blocked' || $event->status === 'partial' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700') }}">
                                            {{ ucfirst($event->status) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-stone-600">{{ $event->created_at->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-10 text-center text-sm text-stone-500">No support events recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</x-admin-layout>
