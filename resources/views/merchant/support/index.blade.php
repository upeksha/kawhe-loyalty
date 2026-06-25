<x-merchant-layout>
    <x-slot name="header">Support Logs</x-slot>

    @php
        $summaryCards = [
            ['label' => 'Matching events', 'value' => number_format($summary['total'] ?? 0), 'tone' => 'bg-brand-50 text-brand-700'],
            ['label' => 'Failed events', 'value' => number_format($summary['failed'] ?? 0), 'tone' => 'bg-red-50 text-red-700'],
            ['label' => 'Needs attention', 'value' => number_format($summary['actionable'] ?? 0), 'tone' => 'bg-amber-50 text-amber-700'],
        ];
    @endphp

    <div class="space-y-6">
        <section class="grid grid-cols-1 gap-4 md:grid-cols-3">
            @foreach($summaryCards as $card)
                <x-ui.section-panel class="p-5">
                    <x-ui.admin-metric :label="$card['label']" :value="$card['value']" :tone="$card['tone']" />
                </x-ui.section-panel>
            @endforeach
        </section>

        <x-ui.section-panel class="p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-stone-900">Support activity across your stores</h2>
                    <p class="mt-1 text-sm text-stone-600">Use this to trace wallet syncs, verification sends, billing issues, and manual support actions without leaving the app.</p>
                </div>
                <form method="GET" action="{{ route('merchant.support.index') }}" class="grid w-full grid-cols-1 gap-3 sm:grid-cols-5 lg:min-w-[920px]">
                    <x-ui.select name="store_id">
                        <option value="">All stores</option>
                        @foreach($stores as $store)
                            <option value="{{ $store->id }}" {{ (string) $activeStoreId === (string) $store->id ? 'selected' : '' }}>{{ $store->name }}</option>
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
                    <x-ui.input type="text" name="q" value="{{ $search }}" placeholder="Customer email, token, manual code" class="rounded-2xl border-stone-200 bg-stone-50 px-4 py-3 shadow-sm shadow-stone-200/30" />
                    <div class="flex gap-2 sm:col-span-5 lg:col-span-1">
                        <x-ui.button type="submit" variant="primary" size="sm" class="flex-1 rounded-2xl">Filter</x-ui.button>
                        <x-ui.button href="{{ route('merchant.support.index') }}" variant="secondary" size="sm" class="flex-1 rounded-2xl">Clear</x-ui.button>
                    </div>
                </form>
            </div>
        </x-ui.section-panel>

        <x-ui.section-panel class="overflow-hidden p-0">
            <x-ui.table>
                <x-ui.table-head>
                    <tr>
                        <x-ui.table-header-cell>Event</x-ui.table-header-cell>
                        <x-ui.table-header-cell>Store</x-ui.table-header-cell>
                        <x-ui.table-header-cell>Customer</x-ui.table-header-cell>
                        <x-ui.table-header-cell>Actor</x-ui.table-header-cell>
                        <x-ui.table-header-cell>Status</x-ui.table-header-cell>
                        <x-ui.table-header-cell>Time</x-ui.table-header-cell>
                    </tr>
                </x-ui.table-head>
                <x-ui.table-body>
                    @forelse($logs as $log)
                        <tr class="transition hover:bg-stone-50">
                            <x-ui.table-cell>
                                <div class="font-medium text-stone-900">{{ str($log->event_type)->replace('_', ' ')->title() }}</div>
                                <div class="mt-1 text-xs text-stone-500">{{ $log->message }}</div>
                            </x-ui.table-cell>
                            <x-ui.table-cell>{{ $log->store->name ?? 'System' }}</x-ui.table-cell>
                            <x-ui.table-cell>{{ $log->loyaltyAccount?->customer?->email ?? 'N/A' }}</x-ui.table-cell>
                            <x-ui.table-cell>{{ $log->actor?->email ?? ($log->source ?? 'system') }}</x-ui.table-cell>
                            <x-ui.table-cell>
                                <x-ui.badge :variant="$log->status === 'failed' ? 'danger' : (in_array($log->status, ['blocked', 'partial'], true) ? 'warning' : 'success')">
                                    {{ ucfirst($log->status) }}
                                </x-ui.badge>
                            </x-ui.table-cell>
                            <x-ui.table-cell>{{ $log->created_at->format('M d, Y g:i A') }}</x-ui.table-cell>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8">
                                <x-ui.empty-state heading="No support events found for the current filters" />
                            </td>
                        </tr>
                    @endforelse
                </x-ui.table-body>
            </x-ui.table>
        </x-ui.section-panel>

        <div class="flex justify-center">
            {{ $logs->links() }}
        </div>
    </div>
</x-merchant-layout>
