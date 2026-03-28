<x-merchant-layout>
    <x-slot name="header">Support Logs</x-slot>

    <div class="space-y-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <x-ui.card class="p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">Matching events</p>
                <p class="mt-2 text-3xl font-bold text-stone-900">{{ $summary['total'] ?? 0 }}</p>
            </x-ui.card>
            <x-ui.card class="p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">Failed events</p>
                <p class="mt-2 text-3xl font-bold text-accent-600">{{ $summary['failed'] ?? 0 }}</p>
            </x-ui.card>
            <x-ui.card class="p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">Needs attention</p>
                <p class="mt-2 text-3xl font-bold text-amber-600">{{ $summary['actionable'] ?? 0 }}</p>
            </x-ui.card>
        </div>

        <x-ui.card class="p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-stone-900">Support activity across your stores</h2>
                    <p class="mt-1 text-sm text-stone-600">Use this to trace wallet syncs, verification sends, billing issues, and manual support actions without leaving the app.</p>
                </div>
                <form method="GET" action="{{ route('merchant.support.index') }}" class="grid grid-cols-1 sm:grid-cols-5 gap-3 w-full lg:w-auto lg:min-w-[920px]">
                    <select name="store_id" class="block w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                        <option value="">All stores</option>
                        @foreach($stores as $store)
                            <option value="{{ $store->id }}" {{ (string) $activeStoreId === (string) $store->id ? 'selected' : '' }}>{{ $store->name }}</option>
                        @endforeach
                    </select>
                    <select name="event_type" class="block w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                        <option value="">All event types</option>
                        <option value="wallet_sync" {{ $eventType === 'wallet_sync' ? 'selected' : '' }}>Wallet sync</option>
                        <option value="verification_send" {{ $eventType === 'verification_send' ? 'selected' : '' }}>Verification send</option>
                        <option value="manual_support_action" {{ $eventType === 'manual_support_action' ? 'selected' : '' }}>Manual support action</option>
                        <option value="welcome_email_send" {{ $eventType === 'welcome_email_send' ? 'selected' : '' }}>Welcome email</option>
                        <option value="store_wallet_refresh" {{ $eventType === 'store_wallet_refresh' ? 'selected' : '' }}>Store wallet refresh</option>
                        <option value="billing_issue" {{ $eventType === 'billing_issue' ? 'selected' : '' }}>Billing issue</option>
                    </select>
                    <select name="status" class="block w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                        <option value="">All statuses</option>
                        <option value="success" {{ $status === 'success' ? 'selected' : '' }}>Success</option>
                        <option value="partial" {{ $status === 'partial' ? 'selected' : '' }}>Partial</option>
                        <option value="blocked" {{ $status === 'blocked' ? 'selected' : '' }}>Blocked</option>
                        <option value="failed" {{ $status === 'failed' ? 'selected' : '' }}>Failed</option>
                    </select>
                    <input type="text" name="q" value="{{ $search }}" placeholder="Customer email, token, manual code" class="block w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                    <div class="flex gap-2 sm:col-span-5 lg:col-span-1">
                        <x-ui.button type="submit" variant="primary" size="sm">Filter</x-ui.button>
                        <x-ui.button href="{{ route('merchant.support.index') }}" variant="secondary" size="sm">Clear</x-ui.button>
                    </div>
                </form>
            </div>
        </x-ui.card>

        <x-ui.card class="p-0 overflow-hidden">
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
                        <tr class="hover:bg-stone-50 transition-colors">
                            <x-ui.table-cell>
                                <div class="font-medium text-stone-900">{{ str($log->event_type)->replace('_', ' ')->title() }}</div>
                                <div class="text-xs text-stone-500 mt-1">{{ $log->message }}</div>
                            </x-ui.table-cell>
                            <x-ui.table-cell>{{ $log->store->name ?? 'System' }}</x-ui.table-cell>
                            <x-ui.table-cell>{{ $log->loyaltyAccount?->customer?->email ?? 'N/A' }}</x-ui.table-cell>
                            <x-ui.table-cell>{{ $log->actor?->email ?? ($log->source ?? 'system') }}</x-ui.table-cell>
                            <x-ui.table-cell>
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ $log->status === 'failed' ? 'bg-red-100 text-red-700' : ($log->status === 'blocked' || $log->status === 'partial' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700') }}">
                                    {{ ucfirst($log->status) }}
                                </span>
                            </x-ui.table-cell>
                            <x-ui.table-cell>{{ $log->created_at->format('M d, Y g:i A') }}</x-ui.table-cell>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-sm text-stone-500">No support events found for the current filters.</td>
                        </tr>
                    @endforelse
                </x-ui.table-body>
            </x-ui.table>
        </x-ui.card>

        <div class="flex justify-center">
            {{ $logs->links() }}
        </div>
    </div>
</x-merchant-layout>
