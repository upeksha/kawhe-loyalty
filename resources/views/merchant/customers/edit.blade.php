<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Edit Customer Information') }}
            </h2>
            <a href="{{ route('merchant.customers.show', $account) }}" class="font-medium text-blue-600 dark:text-blue-500 hover:underline">
                ← Back to Customer Details
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('merchant.customers.update', $account) }}">
                        @csrf
                        @method('PUT')

                        <!-- Success Message -->
                        @if(session('success'))
                            <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                                {{ session('success') }}
                            </div>
                        @endif

                        <!-- Customer Information -->
                        <div class="mb-6">
                            <h3 class="text-lg font-bold mb-4 text-black">Customer Information</h3>
                            
                            <!-- Name -->
                            <div class="mb-4">
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                    Name
                                </label>
                                <input 
                                    type="text" 
                                    id="name" 
                                    name="name" 
                                    value="{{ old('name', $account->customer->name) }}"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('name') border-red-500 @enderror"
                                    placeholder="Customer name"
                                >
                                @error('name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Email -->
                            <div class="mb-4">
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                    Email
                                </label>
                                <input 
                                    type="email" 
                                    id="email" 
                                    name="email" 
                                    value="{{ old('email', $account->customer->email) }}"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('email') border-red-500 @enderror"
                                    placeholder="customer@example.com"
                                >
                                @error('email')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Phone -->
                            <div class="mb-4">
                                <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                                    Phone
                                </label>
                                <input 
                                    type="text" 
                                    id="phone" 
                                    name="phone" 
                                    value="{{ old('phone', $account->customer->phone) }}"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('phone') border-red-500 @enderror"
                                    placeholder="+1 234 567 8900"
                                >
                                @error('phone')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Store Information (Read-only) -->
                        <div class="mb-6 p-4 bg-gray-50 rounded-md">
                            <h4 class="text-sm font-semibold text-gray-700 mb-2">Store</h4>
                            <p class="text-gray-600">{{ $account->store->name }}</p>
                        </div>

                        <!-- Card Status (Read-only) -->
                        <div class="mb-6 p-4 bg-gray-50 rounded-md">
                            <h4 class="text-sm font-semibold text-gray-700 mb-2">Card Status</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-xs text-gray-500">Stamps</p>
                                    <p class="text-lg font-semibold">{{ $account->stamp_count }} / {{ $account->store->reward_target }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Reward Balance</p>
                                    <p class="text-lg font-semibold">{{ $account->reward_balance }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex items-center justify-end gap-4">
                            <a 
                                href="{{ route('merchant.customers.show', $account) }}" 
                                class="px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 font-medium rounded-md transition-colors">
                                Cancel
                            </a>
                            <button 
                                type="submit" 
                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md transition-colors">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
