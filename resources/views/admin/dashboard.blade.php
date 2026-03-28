<x-admin-layout>
    <x-slot name="header">
        {{ __('Super Admin Dashboard') }}
    </x-slot>

    <div class="max-w-7xl mx-auto">
            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500">Total Users</div>
                    <div class="text-3xl font-bold text-gray-900">{{ $stats['total_users'] }}</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500">Total Stores</div>
                    <div class="text-3xl font-bold text-gray-900">{{ $stats['total_stores'] }}</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500">Stamps Today</div>
                    <div class="text-3xl font-bold text-gray-900">{{ $stats['total_stamps_today'] }}</div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500">Support Events (7 days)</div>
                    <div class="text-3xl font-bold text-gray-900">{{ $stats['support_events_last_7_days'] }}</div>
                    <p class="mt-2 text-sm text-gray-600">Includes wallet sync attempts, verification sends, billing failures, and manual support actions.</p>
                </div>
                <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500">Admin Focus</div>
                    <div class="text-lg font-semibold text-gray-900">Merchants with repeated billing or wallet issues</div>
                    <p class="mt-2 text-sm text-gray-600">Use the diagnostics table below to prioritise outreach or deeper investigation.</p>
                </div>
            </div>

            <!-- Recent Stores -->
            <div class="bg-white overflow-hidden shadow-sm rounded-lg mb-8">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Recent Stores</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Store Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Owner</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reward</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse($recent_stores as $store)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-gray-900">{{ $store->name }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ $store->user->email }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ $store->reward_title }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ $store->created_at->diffForHumans() }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-3 text-sm text-gray-500 text-center">No stores yet</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Stamp Activity -->
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Recent Stamp Activity</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Store</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse($recent_stamps as $stamp)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-gray-900">{{ $stamp->loyaltyAccount->customer->email ?? 'N/A' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ $stamp->store->name }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            <span class="px-2 py-1 text-xs rounded-full {{ $stamp->event_type === 'stamp' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                                {{ ucfirst($stamp->event_type) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ $stamp->created_at->diffForHumans() }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-3 text-sm text-gray-500 text-center">No activity yet</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm rounded-lg mt-8">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Merchant Issue Diagnostics (14 days)</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Store</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Owner</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Billing Issues</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Wallet Issues</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Recommended Next Step</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse($merchant_issue_diagnostics as $merchantIssue)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-gray-900">{{ $merchantIssue->name }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ $merchantIssue->email }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ $merchantIssue->billing_issue_count }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ $merchantIssue->wallet_issue_count }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">
                                            @if($merchantIssue->billing_issue_count > 1)
                                                Review Stripe customer + subscription sync first.
                                            @elseif($merchantIssue->wallet_issue_count > 1)
                                                Check wallet registrations and latest sync outcomes.
                                            @else
                                                Review the most recent support events below.
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-3 text-sm text-gray-500 text-center">No repeated merchant billing or wallet issues in the last 14 days.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm rounded-lg mt-8">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Recent Support Events</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Event</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Store</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actor</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse($recent_support_events as $event)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-gray-900">
                                            <div class="font-medium">{{ str($event->event_type)->replace('_', ' ')->title() }}</div>
                                            <div class="text-xs text-gray-500">{{ $event->message }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ $event->store->name ?? 'System' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ $event->actor->email ?? ($event->source ?? 'system') }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            <span class="px-2 py-1 text-xs rounded-full {{ $event->status === 'failed' ? 'bg-red-100 text-red-800' : ($event->status === 'blocked' || $event->status === 'partial' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800') }}">
                                                {{ ucfirst($event->status) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ $event->created_at->diffForHumans() }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-3 text-sm text-gray-500 text-center">No support events recorded yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
