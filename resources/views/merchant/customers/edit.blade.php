<x-merchant-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <span>{{ __('Edit Customer Information') }}</span>
            <x-ui.button href="{{ route('merchant.customers.show', $account) }}" variant="ghost" size="sm">
                ← Back to Customer Details
            </x-ui.button>
        </div>
    </x-slot>

    <div class="max-w-2xl mx-auto">
        <x-ui.card class="p-6">
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
                        <h3 class="text-lg font-bold mb-4 text-stone-900">Customer Information</h3>
                        
                        <!-- Name -->
                        <div class="mb-4">
                            <label for="name" class="block text-sm font-medium text-stone-700 mb-2">
                                Name
                            </label>
                            <x-ui.input 
                                type="text" 
                                id="name" 
                                name="name" 
                                value="{{ old('name', $account->customer->name) }}"
                                placeholder="Customer name"
                                :error="$errors->has('name')"
                            />
                            @error('name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Email -->
                        <div class="mb-4">
                            <label for="email" class="block text-sm font-medium text-stone-700 mb-2">
                                Email
                            </label>
                            <x-ui.input 
                                type="email" 
                                id="email" 
                                name="email" 
                                value="{{ old('email', $account->customer->email) }}"
                                placeholder="customer@example.com"
                                :error="$errors->has('email')"
                            />
                            @error('email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Phone -->
                        <div class="mb-4">
                            <label for="phone" class="block text-sm font-medium text-stone-700 mb-2">
                                Phone
                            </label>
                            <x-ui.input 
                                type="text" 
                                id="phone" 
                                name="phone" 
                                value="{{ old('phone', $account->customer->phone) }}"
                                placeholder="+1 234 567 8900"
                                :error="$errors->has('phone')"
                            />
                            @error('phone')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Store Information (Read-only) -->
                    <div class="mb-6 p-4 bg-stone-50 rounded-md">
                        <h4 class="text-sm font-semibold text-stone-700 mb-2">Store</h4>
                        <p class="text-stone-600">{{ $account->store->name }}</p>
                    </div>

                    <!-- Card Status (Read-only) -->
                    <div class="mb-6 p-4 bg-stone-50 rounded-md">
                        <h4 class="text-sm font-semibold text-stone-700 mb-2">Card Status</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs text-stone-500">Stamps</p>
                                <p class="text-lg font-semibold text-stone-900">{{ $account->stamp_count }} / {{ $account->store->reward_target }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-stone-500">Reward Balance</p>
                                <p class="text-lg font-semibold text-stone-900">{{ $account->reward_balance }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex items-center justify-end gap-4">
                        <x-ui.button href="{{ route('merchant.customers.show', $account) }}" variant="secondary">
                            Cancel
                        </x-ui.button>
                        <x-ui.button type="submit" variant="primary">
                            Save Changes
                        </x-ui.button>
                    </div>
                </form>
            </x-ui.card>
    </div>
</x-merchant-layout>
