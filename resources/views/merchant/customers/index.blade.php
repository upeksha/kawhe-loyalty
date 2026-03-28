<x-merchant-layout>
    <x-slot name="header">
        {{ __('Customers') }}
    </x-slot>

    <div class="space-y-6">
        <x-ui.card class="p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-base font-bold text-stone-900">Customer support lookup</h3>
                    <p class="mt-1 text-sm text-stone-600">Search by customer name, email, phone, manual code, or public token when you need to help someone quickly.</p>
                </div>
                <div class="rounded-xl border border-stone-200 bg-stone-50 px-4 py-3 text-sm text-stone-600">
                    Fastest support path: ask the customer for their <span class="font-semibold text-stone-800">manual code</span> or email.
                </div>
            </div>
        </x-ui.card>

        <!-- Controls Row -->
        <x-ui.card class="p-6" x-data="{ searching: false, filtering: false }">
            <div class="flex flex-col sm:flex-row gap-4">
                <!-- Search Input -->
                <div class="flex-1">
                    <form method="GET" action="{{ route('merchant.customers.index') }}" class="flex gap-2" @submit="searching = true">
                        <x-ui.input 
                            type="text" 
                            name="q" 
                            value="{{ $q }}" 
                            placeholder="Search by name, email, or phone..."
                            placeholder="Search by name, email, phone, manual code, or public token..."
                            class="flex-1"
                            :error="$errors->has('q')"
                        />
                        @if($activeStoreId)
                            <input type="hidden" name="store_id" value="{{ $activeStoreId }}">
                        @endif
                        <x-ui.button type="submit" variant="primary" size="md" x-bind:disabled="searching">
                            <span x-show="!searching">Search</span>
                            <span x-show="searching">Searching...</span>
                        </x-ui.button>
                        @if($q)
                            <x-ui.button href="{{ route('merchant.customers.index', ['store_id' => $activeStoreId]) }}" variant="secondary" size="md">
                                Clear
                            </x-ui.button>
                        @endif
                    </form>
                </div>
                
                <!-- Store Dropdown -->
                <div class="w-full sm:w-auto">
                    <form method="GET" action="{{ route('merchant.customers.index') }}" @submit="filtering = true">
                        @if($q)
                            <input type="hidden" name="q" value="{{ $q }}">
                        @endif
                        <select 
                            name="store_id" 
                            @change="$el.form.requestSubmit()"
                            :disabled="filtering"
                            class="block w-full rounded-lg border border-stone-300 shadow-sm px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500"
                        >
                            <option value="">All Stores</option>
                            @foreach($stores as $store)
                                <option value="{{ $store->id }}" {{ $activeStoreId == $store->id ? 'selected' : '' }}>
                                    {{ $store->name }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                </div>
            </div>
        </x-ui.card>

        <!-- Table -->
        @if($accounts->isEmpty())
            <x-ui.card class="p-12 text-center">
                @if($q || $activeStoreId)
                    <h3 class="text-lg font-semibold text-stone-900">No matching customers</h3>
                    <p class="text-stone-500 mt-2">Try a different search term or clear your filters.</p>
                    <div class="mt-6 flex flex-wrap justify-center gap-2">
                        <x-ui.button href="{{ route('merchant.customers.index') }}" variant="primary" size="sm">
                            Clear Filters
                        </x-ui.button>
                        <x-ui.button href="{{ route('merchant.stores.index') }}" variant="secondary" size="sm">
                            View Store QR
                        </x-ui.button>
                    </div>
                @else
                    <h3 class="text-lg font-semibold text-stone-900">No customers yet</h3>
                    <p class="text-stone-500 mt-2">Share your store join link or QR code and customers will appear here automatically.</p>
                    <div class="mt-6 flex flex-wrap justify-center gap-2">
                        <x-ui.button href="{{ route('merchant.stores.index') }}" variant="primary" size="sm">
                            View Store QR
                        </x-ui.button>
                        <x-ui.button href="{{ route('merchant.scanner') }}" variant="secondary" size="sm">
                            Open Scanner
                        </x-ui.button>
                    </div>
                @endif
            </x-ui.card>
        @else
            <div class="grid grid-cols-1 gap-4 md:hidden">
                @foreach($accounts as $account)
                    <x-ui.card class="p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs text-stone-500">{{ $account->store->name }}</p>
                                <h3 class="font-semibold text-stone-900 mt-1">{{ $account->customer->name ?? '(No name)' }}</h3>
                                <p class="text-sm text-stone-600 mt-1">{{ $account->customer->email ?? '-' }}</p>
                                <p class="text-sm text-stone-500">{{ $account->customer->phone ?? '-' }}</p>
                            </div>
                            <div>
                                @if($account->reward_redeemed_at)
                                    <x-ui.badge variant="default">Redeemed</x-ui.badge>
                                @elseif($account->reward_available_at)
                                    <x-ui.badge variant="success">Available</x-ui.badge>
                                @else
                                    <x-ui.badge variant="default">Not yet</x-ui.badge>
                                @endif
                            </div>
                        </div>
                        <div class="mt-3 text-sm text-stone-600 space-y-1">
                            <p>Manual code: <span class="font-mono font-semibold tracking-wider">{{ $account->manual_entry_code ?? '-' }}</span></p>
                            <p>Stamps: {{ $account->stamp_count }} / {{ $account->store->reward_target }}</p>
                            <p>Joined: {{ $account->created_at->format('M d, Y') }}</p>
                            <p>Last stamped: {{ $account->last_stamped_at ? $account->last_stamped_at->format('M d, Y') : '-' }}</p>
                        </div>
                        <div class="mt-4">
                            <a href="{{ route('merchant.customers.show', $account) }}" class="text-brand-600 hover:text-brand-700 font-medium text-sm">View details</a>
                        </div>
                    </x-ui.card>
                @endforeach
            </div>

            <x-ui.card class="p-0 overflow-hidden hidden md:block">
                <x-ui.table>
                    <x-ui.table-head>
                        <tr>
                            <x-ui.table-header-cell>Store</x-ui.table-header-cell>
                            <x-ui.table-header-cell>Customer</x-ui.table-header-cell>
                            <x-ui.table-header-cell>Email</x-ui.table-header-cell>
                            <x-ui.table-header-cell>Phone</x-ui.table-header-cell>
                            <x-ui.table-header-cell>Birthday</x-ui.table-header-cell>
                            <x-ui.table-header-cell>Manual Code</x-ui.table-header-cell>
                            <x-ui.table-header-cell>Stamps</x-ui.table-header-cell>
                            <x-ui.table-header-cell>Reward</x-ui.table-header-cell>
                            <x-ui.table-header-cell>Last Stamped</x-ui.table-header-cell>
                            <x-ui.table-header-cell>Joined</x-ui.table-header-cell>
                            <x-ui.table-header-cell class="text-right">Action</x-ui.table-header-cell>
                        </tr>
                    </x-ui.table-head>
                    <x-ui.table-body>
                        @foreach($accounts as $account)
                            <tr class="hover:bg-stone-50 transition-colors">
                                <x-ui.table-cell class="font-medium text-stone-900">
                                    {{ $account->store->name }}
                                </x-ui.table-cell>
                                <x-ui.table-cell>
                                    <div class="font-medium text-stone-900">{{ $account->customer->name ?? '(No name)' }}</div>
                                    @if($account->customer->first_name || $account->customer->last_name)
                                        <div class="text-xs text-stone-400">{{ trim(($account->customer->first_name ?? '') . ' ' . ($account->customer->last_name ?? '')) }}</div>
                                    @endif
                                </x-ui.table-cell>
                                <x-ui.table-cell>
                                    {{ $account->customer->email ?? '-' }}
                                </x-ui.table-cell>
                                <x-ui.table-cell>
                                    {{ $account->customer->phone ?? '-' }}
                                </x-ui.table-cell>
                                <x-ui.table-cell>
                                    {{ $account->customer->birthday ? \Carbon\Carbon::parse($account->customer->birthday)->format('M d, Y') : '-' }}
                                </x-ui.table-cell>
                                <x-ui.table-cell>
                                    <span class="font-mono text-xs tracking-wider text-stone-700">{{ $account->manual_entry_code ?? '-' }}</span>
                                </x-ui.table-cell>
                                <x-ui.table-cell>
                                    {{ $account->stamp_count }} / {{ $account->store->reward_target }}
                                </x-ui.table-cell>
                                <x-ui.table-cell>
                                    @if($account->reward_redeemed_at)
                                        <x-ui.badge variant="default">Redeemed</x-ui.badge>
                                    @elseif($account->reward_available_at)
                                        <x-ui.badge variant="success">Available</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="default">Not yet</x-ui.badge>
                                    @endif
                                </x-ui.table-cell>
                                <x-ui.table-cell>
                                    {{ $account->last_stamped_at ? $account->last_stamped_at->format('M d, Y') : '-' }}
                                </x-ui.table-cell>
                                <x-ui.table-cell>
                                    {{ $account->created_at->format('M d, Y') }}
                                </x-ui.table-cell>
                                <x-ui.table-cell class="text-right">
                                    <a href="{{ route('merchant.customers.show', $account) }}" class="text-brand-600 hover:text-brand-700 font-medium text-sm">View</a>
                                </x-ui.table-cell>
                            </tr>
                        @endforeach
                    </x-ui.table-body>
                </x-ui.table>
            </x-ui.card>
            
            <!-- Pagination -->
            <div class="flex justify-center">
                {{ $accounts->links() }}
            </div>
        @endif
    </div>
</x-merchant-layout>
