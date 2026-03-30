<x-admin-layout>
    <x-slot name="header">Support Logs</x-slot>

    @php
        $summaryCards = [
            [
                'label' => 'Matching events',
                'value' => number_format($summary['total'] ?? 0),
                'caption' => 'Events returned by the current filter set.',
                'tone' => 'bg-brand-50 text-brand-700',
            ],
            [
                'label' => 'Failed events',
                'value' => number_format($summary['failed'] ?? 0),
                'caption' => 'Items that need direct follow-up from support.',
                'tone' => 'bg-red-50 text-red-700',
            ],
            [
                'label' => 'Wallet issues',
                'value' => number_format($summary['wallet_issues'] ?? 0),
                'caption' => 'Registration or sync problems across stores.',
                'tone' => 'bg-amber-50 text-amber-700',
            ],
            [
                'label' => 'Billing issues',
                'value' => number_format($summary['billing_issues'] ?? 0),
                'caption' => 'Subscription and Stripe sync issues flagged here.',
                'tone' => 'bg-emerald-50 text-emerald-700',
            ],
        ];
    @endphp

    <div class="mx-auto max-w-7xl space-y-8">
        <section class="rounded-[28px] border border-stone-200/80 bg-white p-6 shadow-sm shadow-stone-200/60 sm:p-8">
            <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-2xl">
                    <p class="text-sm font-semibold uppercase tracking-[0.24em] text-brand-600">Operations</p>
                    <h2 class="mt-3 text-3xl font-semibold tracking-tight text-stone-900 sm:text-4xl">
                        Global support event stream
                    </h2>
                    <p class="mt-3 max-w-xl text-base leading-7 text-stone-600">
                        Review wallet, billing, and manual support activity in one place. Use filters to narrow repeated issues before merchants escalate them.
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:w-[28rem]">
                    <a href="{{ route('admin.support.index', ['issues_only' => 1]) }}" class="rounded-2xl border border-stone-200 bg-stone-50 px-4 py-4 transition hover:border-brand-200 hover:bg-brand-50/60">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Quick Filter</p>
                        <p class="mt-2 text-sm font-semibold text-stone-900">Only show issue events</p>
                    </a>
                    <a href="{{ route('admin.support.index', ['event_type' => 'billing_issue']) }}" class="rounded-2xl border border-stone-200 bg-stone-50 px-4 py-4 transition hover:border-accent-200 hover:bg-accent-50/50">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Quick Filter</p>
                        <p class="mt-2 text-sm font-semibold text-stone-900">Billing issue review</p>
                    </a>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach($summaryCards as $card)
                <article class="rounded-[26px] border border-stone-200/70 bg-white p-5 shadow-sm shadow-stone-200/70">
                    <div class="rounded-2xl {{ $card['tone'] }} p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] opacity-75">{{ $card['label'] }}</p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight text-stone-950">{{ $card['value'] }}</p>
                    </div>
                    <p class="mt-4 text-sm leading-6 text-stone-600">{{ $card['caption'] }}</p>
                </article>
            @endforeach
        </section>

        <section class="rounded-[28px] border border-stone-200/80 bg-white p-6 shadow-sm shadow-stone-200/60">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h3 class="text-xl font-semibold text-stone-900">Filter support events</h3>
                    <p class="mt-2 text-sm leading-6 text-stone-600">Search by store, actor, customer email, public token, or manual entry code.</p>
                </div>

                <form method="GET" action="{{ route('admin.support.index') }}" class="grid w-full gap-3 lg:min-w-[980px] lg:grid-cols-6">
                    <select name="store_id" class="block w-full rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 text-sm text-stone-700 shadow-sm shadow-stone-200/30 focus:border-brand-300 focus:ring-brand-300">
                        <option value="">All stores</option>
                        @foreach($stores as $store)
                            <option value="{{ $store->id }}" {{ (string) $activeStoreId === (string) $store->id ? 'selected' : '' }}>
                                {{ $store->name }}{{ $store->deleted_at ? ' (Archived)' : '' }}
                            </option>
                        @endforeach
                    </select>

                    <select name="event_type" class="block w-full rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 text-sm text-stone-700 shadow-sm shadow-stone-200/30 focus:border-brand-300 focus:ring-brand-300">
                        <option value="">All event types</option>
                        <option value="wallet_sync" {{ $eventType === 'wallet_sync' ? 'selected' : '' }}>Wallet sync</option>
                        <option value="verification_send" {{ $eventType === 'verification_send' ? 'selected' : '' }}>Verification send</option>
                        <option value="manual_support_action" {{ $eventType === 'manual_support_action' ? 'selected' : '' }}>Manual support action</option>
                        <option value="welcome_email_send" {{ $eventType === 'welcome_email_send' ? 'selected' : '' }}>Welcome email</option>
                        <option value="store_wallet_refresh" {{ $eventType === 'store_wallet_refresh' ? 'selected' : '' }}>Store wallet refresh</option>
                        <option value="billing_issue" {{ $eventType === 'billing_issue' ? 'selected' : '' }}>Billing issue</option>
                    </select>

                    <select name="status" class="block w-full rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 text-sm text-stone-700 shadow-sm shadow-stone-200/30 focus:border-brand-300 focus:ring-brand-300">
                        <option value="">All statuses</option>
                        <option value="success" {{ $status === 'success' ? 'selected' : '' }}>Success</option>
                        <option value="partial" {{ $status === 'partial' ? 'selected' : '' }}>Partial</option>
                        <option value="blocked" {{ $status === 'blocked' ? 'selected' : '' }}>Blocked</option>
                        <option value="failed" {{ $status === 'failed' ? 'selected' : '' }}>Failed</option>
                    </select>

                    <label class="inline-flex items-center gap-3 rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 text-sm font-medium text-stone-700 shadow-sm shadow-stone-200/30">
                        <input type="checkbox" name="issues_only" value="1" {{ $issuesOnly ? 'checked' : '' }} class="rounded border-stone-300 text-brand-600 focus:ring-brand-500">
                        Issues only
                    </label>

                    <input type="text" name="q" value="{{ $search }}" placeholder="Store, email, token, code" class="block w-full rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 text-sm text-stone-700 shadow-sm shadow-stone-200/30 placeholder:text-stone-400 focus:border-brand-300 focus:ring-brand-300">

                    <div class="flex gap-2">
                        <button type="submit" class="inline-flex flex-1 items-center justify-center rounded-2xl bg-brand-600 px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-brand-200 transition hover:bg-brand-700">
                            Apply
                        </button>
                        <a href="{{ route('admin.support.index') }}" class="inline-flex flex-1 items-center justify-center rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm font-semibold text-stone-700 transition hover:bg-stone-50">
                            Clear
                        </a>
                    </div>
                </form>
            </div>
        </section>

        <section class="rounded-[28px] border border-stone-200/80 bg-white shadow-sm shadow-stone-200/60">
            <div class="flex items-start justify-between gap-4 border-b border-stone-200/80 px-6 py-5">
                <div>
                    <h3 class="text-xl font-semibold text-stone-900">Support timeline</h3>
                    <p class="mt-2 text-sm leading-6 text-stone-600">Live operational history across stores, accounts, and staff actions.</p>
                </div>
                <span class="hidden rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold text-stone-600 sm:inline-flex">
                    {{ $logs->total() }} events
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200">
                    <thead class="bg-stone-50/90">
                        <tr>
                            <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Event</th>
                            <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Store</th>
                            <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Customer</th>
                            <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Actor</th>
                            <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Status</th>
                            <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-200 bg-white">
                        @forelse($logs as $log)
                            <tr class="align-top transition hover:bg-stone-50/70">
                                <td class="px-5 py-4 text-sm text-stone-900">
                                    <div class="font-semibold">{{ str($log->event_type)->replace('_', ' ')->title() }}</div>
                                    <div class="mt-1 text-xs leading-5 text-stone-500">{{ $log->message }}</div>
                                </td>
                                <td class="px-5 py-4 text-sm text-stone-600">{{ $log->store->name ?? 'System' }}</td>
                                <td class="px-5 py-4 text-sm text-stone-600">{{ $log->loyaltyAccount?->customer?->email ?? 'N/A' }}</td>
                                <td class="px-5 py-4 text-sm text-stone-600">{{ $log->actor?->email ?? ($log->source ?? 'system') }}</td>
                                <td class="px-5 py-4 text-sm">
                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $log->status === 'failed' ? 'bg-red-50 text-red-700' : ($log->status === 'blocked' || $log->status === 'partial' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700') }}">
                                        {{ ucfirst($log->status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-sm text-stone-600">{{ $log->created_at->format('M d, Y g:i A') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-10 text-center text-sm text-stone-500">No support events match the current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="flex justify-center">
            {{ $logs->links() }}
        </div>
    </div>
</x-admin-layout>
