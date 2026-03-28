<x-merchant-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <span>{{ __('Customer Details') }}</span>
            <x-ui.button href="{{ route('merchant.customers.index', request()->query()) }}" variant="ghost" size="sm" class="w-full sm:w-auto">
                ← Back to Customers
            </x-ui.button>
        </div>
    </x-slot>

    <div class="space-y-4 sm:space-y-6">
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            <!-- Store Info Card -->
            <x-ui.card class="p-4 sm:p-6">
                <h3 class="text-base sm:text-lg font-bold mb-3 sm:mb-4 text-stone-900">Store Information</h3>
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
            <x-ui.card class="p-4 sm:p-6">
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 mb-3 sm:mb-4">
                    <h3 class="text-base sm:text-lg font-bold text-stone-900">Customer Information</h3>
                    <x-ui.button href="{{ route('merchant.customers.edit', $account) }}" variant="primary" size="sm" class="w-full sm:w-auto">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        Edit
                    </x-ui.button>
                </div>
                <div class="space-y-2 text-sm">
                    @if($account->customer->first_name || $account->customer->last_name)
                        <p><strong class="text-stone-700">First Name:</strong> <span class="text-stone-600">{{ $account->customer->first_name ?? '-' }}</span></p>
                        <p><strong class="text-stone-700">Last Name:</strong> <span class="text-stone-600">{{ $account->customer->last_name ?? '-' }}</span></p>
                    @else
                        <p><strong class="text-stone-700">Name:</strong> <span class="text-stone-600">{{ $account->customer->name ?? '(No name)' }}</span></p>
                    @endif
                    <p><strong class="text-stone-700">Email:</strong> <span class="text-stone-600">{{ $account->customer->email ?? '-' }}</span></p>
                    <p><strong class="text-stone-700">Phone:</strong> <span class="text-stone-600">{{ $account->customer->phone ?? '-' }}</span></p>
                    @if($account->customer->birthday)
                        <p><strong class="text-stone-700">Birthday:</strong> <span class="text-stone-600">{{ \Carbon\Carbon::parse($account->customer->birthday)->format('M d, Y') }}</span></p>
                    @endif
                    <p><strong class="text-stone-700">Joined:</strong> <span class="text-stone-600">{{ $account->created_at->format('M d, Y g:i A') }}</span></p>
                    @if($account->verified_at)
                        <p><strong class="text-stone-700">Verified:</strong> <span class="text-brand-600">{{ $account->verified_at->format('M d, Y') }}</span></p>
                    @endif
                </div>
            </x-ui.card>

            <x-ui.card class="p-4 sm:p-6">
                <h3 class="text-base sm:text-lg font-bold mb-3 sm:mb-4 text-stone-900">Support Snapshot</h3>
                <div class="space-y-2 text-sm">
                    <p><strong class="text-stone-700">Manual Code:</strong> <span class="font-mono font-semibold tracking-widest text-stone-900">{{ $account->manual_entry_code ?? '-' }}</span></p>
                    <p><strong class="text-stone-700">Public Token:</strong> <span class="font-mono text-xs text-stone-600 break-all">{{ $account->public_token }}</span></p>
                    <p><strong class="text-stone-700">Verification:</strong>
                        <span class="{{ $account->verified_at ? 'text-emerald-700' : 'text-amber-700' }}">
                            {{ $account->verified_at ? 'Verified' : 'Not verified' }}
                        </span>
                    </p>
                    <p><strong class="text-stone-700">Card URL:</strong>
                        <a href="{{ route('card.show', $account->public_token) }}" target="_blank" class="text-brand-600 hover:text-brand-700 underline">Open card</a>
                    </p>
                </div>
                <div class="mt-4 rounded-xl border border-stone-200 bg-stone-50/70 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Support tip</p>
                    <p class="mt-2 text-sm leading-relaxed text-stone-600">
                        If a customer is standing at the counter, the manual code is the fastest fallback. If they are remote, ask for the email address used to join.
                    </p>
                </div>
            </x-ui.card>
        </div>

        <!-- Card Status Card -->
        <x-ui.card class="p-4 sm:p-6">
            <h3 class="text-base sm:text-lg font-bold mb-3 sm:mb-4 text-stone-900">Card Status</h3>
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
        <x-ui.card class="p-4 sm:p-6">
            <h3 class="text-base sm:text-lg font-bold mb-3 sm:mb-4 text-stone-900">Recent Activity</h3>
            @if($events->isEmpty())
                <p class="text-stone-500 text-center py-4">No activity recorded yet.</p>
            @else
                <div class="grid grid-cols-1 gap-3 md:hidden">
                    @foreach($events as $event)
                        <div class="rounded-xl border border-stone-200 bg-stone-50/60 p-4">
                            <div class="flex items-center justify-between gap-2">
                                <x-ui.badge variant="{{ $event->type === 'stamp' ? 'info' : 'success' }}">
                                    {{ ucfirst($event->type) }}
                                </x-ui.badge>
                                <p class="text-xs text-stone-500">{{ $event->created_at->format('M d, Y g:i A') }}</p>
                            </div>
                            <div class="mt-2 text-sm text-stone-700 space-y-1">
                                <p><strong>Count:</strong> {{ $event->count ?? '-' }}</p>
                                <p><strong>By:</strong> {{ $event->user->name ?? 'System' }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
                <x-ui.table class="hidden md:table">
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
