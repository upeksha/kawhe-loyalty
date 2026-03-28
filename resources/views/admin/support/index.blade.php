<x-admin-layout>
    <x-slot name="header">Support Logs</x-slot>

    <div class="space-y-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <div class="bg-white overflow-hidden shadow-sm rounded-lg p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Matching events</p>
                <p class="mt-2 text-3xl font-bold text-gray-900">{{ $summary['total'] ?? 0 }}</p>
            </div>
            <div class="bg-white overflow-hidden shadow-sm rounded-lg p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Failed events</p>
                <p class="mt-2 text-3xl font-bold text-red-600">{{ $summary['failed'] ?? 0 }}</p>
            </div>
            <div class="bg-white overflow-hidden shadow-sm rounded-lg p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Wallet issues</p>
                <p class="mt-2 text-3xl font-bold text-amber-600">{{ $summary['wallet_issues'] ?? 0 }}</p>
            </div>
            <div class="bg-white overflow-hidden shadow-sm rounded-lg p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Billing issues</p>
                <p class="mt-2 text-3xl font-bold text-accent-600">{{ $summary['billing_issues'] ?? 0 }}</p>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Global support event stream</h2>
                    <p class="mt-1 text-sm text-gray-600">Filter down to repeated billing and wallet issues or review the full operational trail.</p>
                </div>
                <form method="GET" action="{{ route('admin.support.index') }}" class="grid grid-cols-1 sm:grid-cols-5 gap-3 w-full lg:w-auto lg:min-w-[980px]">
                    <select name="store_id" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        <option value="">All stores</option>
                        @foreach($stores as $store)
                            <option value="{{ $store->id }}" {{ (string) $activeStoreId === (string) $store->id ? 'selected' : '' }}>
                                {{ $store->name }}{{ $store->deleted_at ? ' (Archived)' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <select name="event_type" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        <option value="">All event types</option>
                        <option value="wallet_sync" {{ $eventType === 'wallet_sync' ? 'selected' : '' }}>Wallet sync</option>
                        <option value="verification_send" {{ $eventType === 'verification_send' ? 'selected' : '' }}>Verification send</option>
                        <option value="manual_support_action" {{ $eventType === 'manual_support_action' ? 'selected' : '' }}>Manual support action</option>
                        <option value="welcome_email_send" {{ $eventType === 'welcome_email_send' ? 'selected' : '' }}>Welcome email</option>
                        <option value="store_wallet_refresh" {{ $eventType === 'store_wallet_refresh' ? 'selected' : '' }}>Store wallet refresh</option>
                        <option value="billing_issue" {{ $eventType === 'billing_issue' ? 'selected' : '' }}>Billing issue</option>
                    </select>
                    <select name="status" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        <option value="">All statuses</option>
                        <option value="success" {{ $status === 'success' ? 'selected' : '' }}>Success</option>
                        <option value="partial" {{ $status === 'partial' ? 'selected' : '' }}>Partial</option>
                        <option value="blocked" {{ $status === 'blocked' ? 'selected' : '' }}>Blocked</option>
                        <option value="failed" {{ $status === 'failed' ? 'selected' : '' }}>Failed</option>
                    </select>
                    <label class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700">
                        <input type="checkbox" name="issues_only" value="1" {{ $issuesOnly ? 'checked' : '' }}>
                        Issues only
                    </label>
                    <input type="text" name="q" value="{{ $search }}" placeholder="Store, email, token, code" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <div class="flex gap-2 sm:col-span-5 lg:col-span-1">
                        <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Filter</button>
                        <a href="{{ route('admin.support.index') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-sm rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Event</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Store</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actor</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($logs as $log)
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    <div class="font-medium">{{ str($log->event_type)->replace('_', ' ')->title() }}</div>
                                    <div class="text-xs text-gray-500 mt-1">{{ $log->message }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $log->store->name ?? 'System' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $log->loyaltyAccount?->customer?->email ?? 'N/A' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $log->actor?->email ?? ($log->source ?? 'system') }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="px-2 py-1 text-xs rounded-full {{ $log->status === 'failed' ? 'bg-red-100 text-red-800' : ($log->status === 'blocked' || $log->status === 'partial' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800') }}">
                                        {{ ucfirst($log->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $log->created_at->format('M d, Y g:i A') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">No support events match the current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex justify-center">
            {{ $logs->links() }}
        </div>
    </div>
</x-admin-layout>
