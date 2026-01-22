<x-merchant-layout>
    <x-slot name="header">
        {{ __('Customers') }}
    </x-slot>

    <div class="space-y-6">
        <!-- Controls Row -->
        <x-ui.card class="p-6">
            <div class="flex flex-col sm:flex-row gap-4">
                <!-- Search Input -->
                <div class="flex-1">
                    <form method="GET" action="{{ route('merchant.customers.index') }}" class="flex gap-2">
                        <x-ui.input 
                            type="text" 
                            name="q" 
                            value="{{ $q }}" 
                            placeholder="Search by name, email, or phone..."
                            class="flex-1"
                            :error="$errors->has('q')"
                        />
                        @if($activeStoreId)
                            <input type="hidden" name="store_id" value="{{ $activeStoreId }}">
                        @endif
                        <x-ui.button type="submit" variant="primary" size="md">
                            Search
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
                    <form method="GET" action="{{ route('merchant.customers.index') }}" id="storeFilterForm">
                        @if($q)
                            <input type="hidden" name="q" value="{{ $q }}">
                        @endif
                        <select 
                            name="store_id" 
                            onchange="document.getElementById('storeFilterForm').submit();"
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
                <p class="text-stone-500">No customers found.</p>
            </x-ui.card>
        @else
            <x-ui.card class="p-0 overflow-hidden">
                <x-ui.table>
                    <x-ui.table-head>
                        <tr>
                            <x-ui.table-header-cell>Store</x-ui.table-header-cell>
                            <x-ui.table-header-cell>Customer</x-ui.table-header-cell>
                            <x-ui.table-header-cell>Email</x-ui.table-header-cell>
                            <x-ui.table-header-cell>Phone</x-ui.table-header-cell>
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
                                    {{ $account->customer->name ?? '(No name)' }}
                                </x-ui.table-cell>
                                <x-ui.table-cell>
                                    {{ $account->customer->email ?? '-' }}
                                </x-ui.table-cell>
                                <x-ui.table-cell>
                                    {{ $account->customer->phone ?? '-' }}
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


