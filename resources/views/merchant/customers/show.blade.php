<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Customer Details') }}
            </h2>
            <a href="{{ route('merchant.customers.index', request()->query()) }}" class="font-medium text-blue-600 dark:text-blue-500 hover:underline">
                ← Back to Customers
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- Store Info Card -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h3 class="text-lg font-bold mb-4 text-black">Store Information</h3>
                        <p class="mb-2"><strong>Name:</strong> {{ $account->store->name }}</p>
                        @if($account->store->address)
                            <p class="mb-2"><strong>Address:</strong> {{ $account->store->address }}</p>
                        @endif
                        <p class="mb-2"><strong>Reward Target:</strong> {{ $account->store->reward_target }} stamps</p>
                        <p><strong>Reward:</strong> {{ $account->store->reward_title }}</p>
                    </div>
                </div>

                <!-- Customer Info Card -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h3 class="text-lg font-bold mb-4 text-black">Customer Information</h3>
                        <p class="mb-2"><strong>Name:</strong> {{ $account->customer->name ?? '(No name)' }}</p>
                        <p class="mb-2"><strong>Email:</strong> {{ $account->customer->email ?? '-' }}</p>
                        <p class="mb-2"><strong>Phone:</strong> {{ $account->customer->phone ?? '-' }}</p>
                        <p class="mb-2"><strong>Joined:</strong> {{ $account->created_at->format('M d, Y g:i A') }}</p>
                        @if($account->verified_at)
                            <p class="text-green-600"><strong>Verified:</strong> {{ $account->verified_at->format('M d, Y') }}</p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Card Status Card -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-bold mb-4 text-black">Card Status</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <p class="text-sm text-gray-500">Stamps</p>
                            <p class="text-2xl font-bold">{{ $account->stamp_count }} / {{ $account->store->reward_target }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Reward Status</p>
                            @if($account->reward_redeemed_at)
                                <p class="text-lg font-semibold text-gray-500">Redeemed</p>
                                <p class="text-xs text-gray-400">{{ $account->reward_redeemed_at->format('M d, Y g:i A') }}</p>
                            @elseif($account->reward_available_at)
                                <p class="text-lg font-semibold text-green-600">Available</p>
                                <p class="text-xs text-gray-400">Since {{ $account->reward_available_at->format('M d, Y') }}</p>
                            @else
                                <p class="text-lg font-semibold text-gray-400">Not yet</p>
                                <p class="text-xs text-gray-400">{{ max(0, $account->store->reward_target - $account->stamp_count) }} more needed</p>
                            @endif
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Last Stamped</p>
                            @if($account->last_stamped_at)
                                <p class="text-lg font-semibold">{{ $account->last_stamped_at->format('M d, Y') }}</p>
                                <p class="text-xs text-gray-400">{{ $account->last_stamped_at->format('g:i A') }}</p>
                            @else
                                <p class="text-lg font-semibold text-gray-400">Never</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activity Table -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-bold mb-4 text-black">Recent Activity</h3>
                    @if($events->isEmpty())
                        <p class="text-gray-500 text-center py-4">No activity recorded yet.</p>
                    @else
                        <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                            <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th scope="col" class="px-6 py-3">Type</th>
                                        <th scope="col" class="px-6 py-3">Count</th>
                                        <th scope="col" class="px-6 py-3">By</th>
                                        <th scope="col" class="px-6 py-3">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($events as $event)
                                        <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                                            <td class="px-6 py-4">
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $event->type === 'stamp' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' }}">
                                                    {{ ucfirst($event->type) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                {{ $event->count ?? '-' }}
                                            </td>
                                            <td class="px-6 py-4">
                                                {{ $event->user->name ?? 'System' }}
                                            </td>
                                            <td class="px-6 py-4">
                                                {{ $event->created_at->format('M d, Y g:i A') }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>


