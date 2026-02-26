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
                    <form method="POST" action="{{ route('merchant.customers.update', $account) }}" id="customer-edit-form">
                        @csrf
                        @method('PUT')
                        <x-form-error-summary form-id="customer-edit-form" />

                    <!-- Success Message -->
                    @if(session('success'))
                        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                            {{ session('success') }}
                        </div>
                    @endif

                    <!-- Customer Information -->
                    <div class="mb-6">
                        <h3 class="text-lg font-bold mb-4 text-stone-900">Customer Information</h3>

                        <!-- First Name & Last Name -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-stone-700 mb-2">First Name</label>
                                <x-ui.input
                                    type="text"
                                    id="first_name"
                                    name="first_name"
                                    value="{{ old('first_name', $account->customer->first_name) }}"
                                    placeholder="First name"
                                    :error="$errors->has('first_name')"
                                />
                                @error('first_name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="last_name" class="block text-sm font-medium text-stone-700 mb-2">Last Name</label>
                                <x-ui.input
                                    type="text"
                                    id="last_name"
                                    name="last_name"
                                    value="{{ old('last_name', $account->customer->last_name) }}"
                                    placeholder="Last name"
                                    :error="$errors->has('last_name')"
                                />
                                @error('last_name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Email -->
                        <div class="mb-4">
                            <label for="email" class="block text-sm font-medium text-stone-700 mb-2">Email</label>
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
                            <label for="phone" class="block text-sm font-medium text-stone-700 mb-2">Phone</label>
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

                        <!-- Birthday -->
                        <div class="mb-4">
                            <label for="birthday" class="block text-sm font-medium text-stone-700 mb-2">Birthday</label>
                            <x-ui.input
                                type="date"
                                id="birthday"
                                name="birthday"
                                value="{{ old('birthday', $account->customer->birthday ? \Carbon\Carbon::parse($account->customer->birthday)->format('Y-m-d') : '') }}"
                                :error="$errors->has('birthday')"
                            />
                            @error('birthday')
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
