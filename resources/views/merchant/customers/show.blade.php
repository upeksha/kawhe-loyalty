<x-merchant-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <span>{{ __('Customer Details') }}</span>
            <x-ui.button href="{{ route('merchant.customers.index', request()->query()) }}" variant="ghost" size="sm">
                ← Back to Customers
            </x-ui.button>
        </div>
    </x-slot>

    <div class="space-y-6">
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Store Info Card -->
            <x-ui.card class="p-6">
                <h3 class="text-lg font-bold mb-4 text-stone-900">Store Information</h3>
                <div class="space-y-2 text-sm">
                    <p><strong class="text-stone-700">Name:</strong> <span class="text-stone-600">{{ $account->store->name }}</span></p>
                    @if($account->store->address)
                        <p><strong class="text-stone-700">Address:</strong> <span class="text-stone-600">{{ $account->store->address }}</span></p>
                    @endif
                    <p><strong class="text-stone-700">Reward Target:</strong> <span class="text-stone-600">{{ $account->store->reward_target }} stamps</span></p>
                    <p><strong class="text-stone-700">Reward:</strong> <span class="text-stone-600">{{ $account->store->reward_title }}</span></p>
                </div>
            </x-ui.card>

            <!-- Customer Info Card -->
            <x-ui.card class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-stone-900">Customer Information</h3>
                    <x-ui.button href="{{ route('merchant.customers.edit', $account) }}" variant="primary" size="sm">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        Edit
                    </x-ui.button>
                </div>
                <div class="space-y-2 text-sm">
                    <p><strong class="text-stone-700">Name:</strong> <span class="text-stone-600">{{ $account->customer->name ?? '(No name)' }}</span></p>
                    <p><strong class="text-stone-700">Email:</strong> <span class="text-stone-600">{{ $account->customer->email ?? '-' }}</span></p>
                    <p><strong class="text-stone-700">Phone:</strong> <span class="text-stone-600">{{ $account->customer->phone ?? '-' }}</span></p>
                    <p><strong class="text-stone-700">Joined:</strong> <span class="text-stone-600">{{ $account->created_at->format('M d, Y g:i A') }}</span></p>
                    @if($account->verified_at)
                        <p><strong class="text-stone-700">Verified:</strong> <span class="text-brand-600">{{ $account->verified_at->format('M d, Y') }}</span></p>
                    @endif
                </div>
            </x-ui.card>
        </div>

        <!-- Card Status Card -->
        <x-ui.card class="p-6">
            <h3 class="text-lg font-bold mb-4 text-stone-900">Card Status</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <p class="text-sm text-stone-500 mb-1">Stamps</p>
                    <p class="text-2xl font-bold text-stone-900">{{ $account->stamp_count }} / {{ $account->store->reward_target }}</p>
                </div>
                <div>
                    <p class="text-sm text-stone-500 mb-1">Reward Status</p>
                    @if($account->reward_redeemed_at)
                        <p class="text-lg font-semibold text-stone-500">Redeemed</p>
                        <p class="text-xs text-stone-400">{{ $account->reward_redeemed_at->format('M d, Y g:i A') }}</p>
                    @elseif($account->reward_available_at)
                        <p class="text-lg font-semibold text-brand-600">Available</p>
                        <p class="text-xs text-stone-400">Since {{ $account->reward_available_at->format('M d, Y') }}</p>
                    @else
                        <p class="text-lg font-semibold text-stone-400">Not yet</p>
                        <p class="text-xs text-stone-400">{{ max(0, $account->store->reward_target - $account->stamp_count) }} more needed</p>
                    @endif
                </div>
                <div>
                    <p class="text-sm text-stone-500 mb-1">Last Stamped</p>
                    @if($account->last_stamped_at)
                        <p class="text-lg font-semibold text-stone-900">{{ $account->last_stamped_at->format('M d, Y') }}</p>
                        <p class="text-xs text-stone-400">{{ $account->last_stamped_at->format('g:i A') }}</p>
                    @else
                        <p class="text-lg font-semibold text-stone-400">Never</p>
                    @endif
                </div>
            </div>
        </x-ui.card>

        <!-- Activity Table -->
        <x-ui.card class="p-6">
            <h3 class="text-lg font-bold mb-4 text-stone-900">Recent Activity</h3>
            @if($events->isEmpty())
                <p class="text-stone-500 text-center py-4">No activity recorded yet.</p>
            @else
                <x-ui.table>
                    <x-ui.table-head>
                        <tr>
                            <x-ui.table-header-cell>Type</x-ui.table-header-cell>
                            <x-ui.table-header-cell>Count</x-ui.table-header-cell>
                            <x-ui.table-header-cell>By</x-ui.table-header-cell>
                            <x-ui.table-header-cell>Date</x-ui.table-header-cell>
                        </tr>
                    </x-ui.table-head>
                    <x-ui.table-body>
                        @foreach($events as $event)
                            <tr class="hover:bg-stone-50 transition-colors">
                                <x-ui.table-cell>
                                    <x-ui.badge variant="{{ $event->type === 'stamp' ? 'info' : 'success' }}">
                                        {{ ucfirst($event->type) }}
                                    </x-ui.badge>
                                </x-ui.table-cell>
                                <x-ui.table-cell>
                                    {{ $event->count ?? '-' }}
                                </x-ui.table-cell>
                                <x-ui.table-cell>
                                    {{ $event->user->name ?? 'System' }}
                                </x-ui.table-cell>
                                <x-ui.table-cell>
                                    {{ $event->created_at->format('M d, Y g:i A') }}
                                </x-ui.table-cell>
                            </tr>
                        @endforeach
                    </x-ui.table-body>
                </x-ui.table>
            @endif
        </x-ui.card>
    </div>
</x-merchant-layout>


