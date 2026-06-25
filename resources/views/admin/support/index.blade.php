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
        <x-ui.page-hero
            eyebrow="Operations"
            title="Global support event stream"
            description="Review wallet, billing, and manual support activity in one place. Use filters to narrow repeated issues before merchants escalate them."
        >
            <x-slot name="actions">
                <x-ui.quick-link href="{{ route('admin.support.index', ['issues_only' => 1]) }}" label="Quick Filter" title="Only show issue events" />
                <x-ui.quick-link href="{{ route('admin.support.index', ['event_type' => 'billing_issue']) }}" label="Quick Filter" title="Billing issue review" hover="accent" />
            </x-slot>
        </x-ui.page-hero>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach($summaryCards as $card)
                <article class="rounded-[26px] border border-stone-200/70 bg-white p-5 shadow-sm shadow-stone-200/70">
                    <x-ui.admin-metric :label="$card['label']" :value="$card['value']" :tone="$card['tone']" />
                    <p class="mt-4 text-sm leading-6 text-stone-600">{{ $card['caption'] }}</p>
                </article>
            @endforeach
        </section>

        <x-ui.section-panel class="p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h3 class="text-xl font-semibold text-stone-900">Filter support events</h3>
                    <p class="mt-2 text-sm leading-6 text-stone-600">Search by store, actor, customer email, public token, or manual entry code.</p>
                </div>

                <form method="GET" action="{{ route('admin.support.index') }}" class="grid w-full gap-3 lg:min-w-[980px] lg:grid-cols-6">
                    <x-ui.select name="store_id">
                        <option value="">All stores</option>
                        @foreach($stores as $store)
                            <option value="{{ $store->id }}" {{ (string) $activeStoreId === (string) $store->id ? 'selected' : '' }}>
                                {{ $store->name }}{{ $store->deleted_at ? ' (Archived)' : '' }}
                            </option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.select name="event_type">
                        <option value="">All event types</option>
                        <option value="wallet_sync" {{ $eventType === 'wallet_sync' ? 'selected' : '' }}>Wallet sync</option>
                        <option value="verification_send" {{ $eventType === 'verification_send' ? 'selected' : '' }}>Verification send</option>
                        <option value="manual_support_action" {{ $eventType === 'manual_support_action' ? 'selected' : '' }}>Manual support action</option>
                        <option value="welcome_email_send" {{ $eventType === 'welcome_email_send' ? 'selected' : '' }}>Welcome email</option>
                        <option value="store_wallet_refresh" {{ $eventType === 'store_wallet_refresh' ? 'selected' : '' }}>Store wallet refresh</option>
                        <option value="billing_issue" {{ $eventType === 'billing_issue' ? 'selected' : '' }}>Billing issue</option>
                    </x-ui.select>

                    <x-ui.select name="status">
                        <option value="">All statuses</option>
                        <option value="success" {{ $status === 'success' ? 'selected' : '' }}>Success</option>
                        <option value="partial" {{ $status === 'partial' ? 'selected' : '' }}>Partial</option>
                        <option value="blocked" {{ $status === 'blocked' ? 'selected' : '' }}>Blocked</option>
                        <option value="failed" {{ $status === 'failed' ? 'selected' : '' }}>Failed</option>
                    </x-ui.select>

                    <label class="inline-flex items-center gap-3 rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 text-sm font-medium text-stone-700 shadow-sm shadow-stone-200/30">
                        <input type="checkbox" name="issues_only" value="1" {{ $issuesOnly ? 'checked' : '' }} class="rounded border-stone-300 text-brand-600 focus:ring-brand-500">
                        Issues only
                    </label>

                    <x-ui.input type="text" name="q" value="{{ $search }}" placeholder="Store, email, token, code" class="rounded-2xl border-stone-200 bg-stone-50 px-4 py-3 shadow-sm shadow-stone-200/30 placeholder:text-stone-400" />

                    <div class="flex gap-2">
                        <x-ui.button type="submit" variant="primary" size="md" class="flex-1 rounded-2xl py-3">
                            Apply
                        </x-ui.button>
                        <x-ui.button href="{{ route('admin.support.index') }}" variant="secondary" size="md" class="flex-1 rounded-2xl py-3">
                            Clear
                        </x-ui.button>
                    </div>
                </form>
            </div>
        </x-ui.section-panel>

        <x-ui.section-panel class="overflow-hidden p-0">
            <div class="flex items-start justify-between gap-4 border-b border-stone-200/80 px-6 py-5">
                <div>
                    <h3 class="text-xl font-semibold text-stone-900">Support timeline</h3>
                    <p class="mt-2 text-sm leading-6 text-stone-600">Live operational history across stores, accounts, and staff actions.</p>
                </div>
                <x-ui.badge variant="default" class="hidden sm:inline-flex">
                    {{ $logs->total() }} events
                </x-ui.badge>
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
                                    <x-ui.badge :variant="$log->status === 'failed' ? 'danger' : (in_array($log->status, ['blocked', 'partial'], true) ? 'warning' : 'success')">
                                        {{ ucfirst($log->status) }}
                                    </x-ui.badge>
                                </td>
                                <td class="px-5 py-4 text-sm text-stone-600">{{ $log->created_at->format('M d, Y g:i A') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-10">
                                    <x-ui.empty-state heading="No support events match the current filters" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.section-panel>

        <div class="flex justify-center">
            {{ $logs->links() }}
        </div>
    </div>
</x-admin-layout>
