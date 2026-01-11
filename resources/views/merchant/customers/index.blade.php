<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Customers') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <!-- Controls Row -->
                    <div class="mb-6 flex flex-col sm:flex-row gap-4">
                        <!-- Search Input -->
                        <div class="flex-1">
                            <form method="GET" action="{{ route('merchant.customers.index') }}" class="flex gap-2">
                                <input 
                                    type="text" 
                                    name="q" 
                                    value="{{ $q }}" 
                                    placeholder="Search by name, email, or phone..." 
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                                >
                                @if($activeStoreId)
                                    <input type="hidden" name="store_id" value="{{ $activeStoreId }}">
                                @endif
                                <button type="submit" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                                    Search
                                </button>
                                @if($q)
                                    <a href="{{ route('merchant.customers.index', ['store_id' => $activeStoreId]) }}" class="text-white bg-gray-500 hover:bg-gray-600 focus:ring-4 focus:outline-none focus:ring-gray-300 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-gray-600 dark:hover:bg-gray-700 dark:focus:ring-gray-800">
                                        Clear
                                    </a>
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
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
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

                    <!-- Table -->
                    @if($accounts->isEmpty())
                        <p class="text-gray-500 text-center py-4">No customers found.</p>
                    @else
                        <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                            <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th scope="col" class="px-6 py-3">Store</th>
                                        <th scope="col" class="px-6 py-3">Customer</th>
                                        <th scope="col" class="px-6 py-3">Email</th>
                                        <th scope="col" class="px-6 py-3">Phone</th>
                                        <th scope="col" class="px-6 py-3">Stamps</th>
                                        <th scope="col" class="px-6 py-3">Reward</th>
                                        <th scope="col" class="px-6 py-3">Last Stamped</th>
                                        <th scope="col" class="px-6 py-3">Joined</th>
                                        <th scope="col" class="px-6 py-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($accounts as $account)
                                        <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                            <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                                {{ $account->store->name }}
                                            </td>
                                            <td class="px-6 py-4">
                                                {{ $account->customer->name ?? '(No name)' }}
                                            </td>
                                            <td class="px-6 py-4">
                                                {{ $account->customer->email ?? '-' }}
                                            </td>
                                            <td class="px-6 py-4">
                                                {{ $account->customer->phone ?? '-' }}
                                            </td>
                                            <td class="px-6 py-4">
                                                {{ $account->stamp_count }} / {{ $account->store->reward_target }}
                                            </td>
                                            <td class="px-6 py-4">
                                                @if($account->reward_redeemed_at)
                                                    <span class="text-gray-500">Redeemed</span>
                                                @elseif($account->reward_available_at)
                                                    <span class="text-green-600 font-semibold">Available</span>
                                                @else
                                                    <span class="text-gray-400">Not yet</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4">
                                                {{ $account->last_stamped_at ? $account->last_stamped_at->format('M d, Y') : '-' }}
                                            </td>
                                            <td class="px-6 py-4">
                                                {{ $account->created_at->format('M d, Y') }}
                                            </td>
                                            <td class="px-6 py-4">
                                                <a href="{{ route('merchant.customers.show', $account) }}" class="font-medium text-blue-600 dark:text-blue-500 hover:underline">View</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <div class="mt-4">
                            {{ $accounts->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>


