<x-merchant-layout>
    <x-slot name="header">
        {{ __('Edit Store') }}
    </x-slot>

    <div class="max-w-2xl mx-auto">
        <x-ui.card class="p-6">
                    <form method="POST" action="{{ route('merchant.stores.update', $store) }}" enctype="multipart/form-data" class="max-w-md mx-auto">
                        @csrf
                        @method('PUT')

                        <!-- Name -->
                        <div class="mb-5">
                            <label for="name" class="block mb-2 text-sm font-medium text-stone-700">Store Name</label>
                            <x-ui.input type="text" id="name" name="name" value="{{ old('name', $store->name) }}" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <!-- Address -->
                        <div class="mb-5">
                            <label for="address" class="block mb-2 text-sm font-medium text-stone-700">Address (Optional)</label>
                            <x-ui.input type="text" id="address" name="address" value="{{ old('address', $store->address) }}" />
                            <x-input-error :messages="$errors->get('address')" class="mt-2" />
                        </div>

                        <!-- Reward Target -->
                        <div class="mb-5">
                            <label for="reward_target" class="block mb-2 text-sm font-medium text-stone-700">Stamps needed for reward</label>
                            <x-ui.input type="number" id="reward_target" name="reward_target" value="{{ old('reward_target', $store->reward_target) }}" min="1" required />
                            <x-input-error :messages="$errors->get('reward_target')" class="mt-2" />
                        </div>

                        <!-- Reward Title -->
                        <div class="mb-5">
                            <label for="reward_title" class="block mb-2 text-sm font-medium text-stone-700">Reward Title</label>
                            <x-ui.input type="text" id="reward_title" name="reward_title" value="{{ old('reward_title', $store->reward_title) }}" required />
                            <x-input-error :messages="$errors->get('reward_title')" class="mt-2" />
                        </div>

                        <!-- Require Verification for Redemption -->
                        <div class="mb-5" x-data="{ requireVerification: {{ old('require_verification_for_redemption', $store->require_verification_for_redemption ?? true) ? 'true' : 'false' }} }">
                            <div class="flex items-start gap-3">
                                <div class="flex items-center h-5">
                                    <input 
                                        type="checkbox" 
                                        id="require_verification_for_redemption" 
                                        name="require_verification_for_redemption" 
                                        value="1"
                                        x-model="requireVerification"
                                        class="w-4 h-4 text-brand-600 border-stone-300 rounded focus:ring-brand-500"
                                        {{ old('require_verification_for_redemption', $store->require_verification_for_redemption ?? true) ? 'checked' : '' }}
                                    />
                                </div>
                                <div class="flex-1">
                                    <label for="require_verification_for_redemption" class="block text-sm font-medium text-stone-700 cursor-pointer">
                                        Require Email Verification for Redemption
                                    </label>
                                    <p class="mt-1 text-xs text-stone-500">
                                        If enabled, customers must verify their email address before redeeming rewards. This helps prevent fraud and ensures rewards go to the correct customer.
                                    </p>
                                    <!-- Warning when disabled -->
                                    <div x-show="!requireVerification" x-cloak class="mt-2 p-3 bg-accent-50 border-l-4 border-accent-500 rounded-r">
                                        <div class="flex items-start gap-2">
                                            <svg class="w-5 h-5 text-accent-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                            </svg>
                                            <div>
                                                <p class="text-sm font-semibold text-accent-800">Security Warning</p>
                                                <p class="text-xs text-accent-700 mt-1">
                                                    Allowing unverified redemption may increase fraud risk. Only disable this if you verify customers in person at your store.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <x-input-error :messages="$errors->get('require_verification_for_redemption')" class="mt-2" />
                        </div>

                        <!-- Brand Color -->
                        <div class="mb-5">
                            <label for="brand_color" class="block mb-2 text-sm font-medium text-stone-700">Brand Color</label>
                            <div class="flex gap-2">
                                <input type="color" id="brand_color" name="brand_color" value="{{ old('brand_color', $store->brand_color ?? '#0EA5E9') }}" class="h-10 w-20 rounded border border-stone-300 cursor-pointer">
                                <x-ui.input type="text" id="brand_color_text" value="{{ old('brand_color', $store->brand_color ?? '#0EA5E9') }}" placeholder="#0EA5E9" pattern="^#[0-9A-Fa-f]{6}$" class="flex-1" />
                            </div>
                            <p class="mt-1 text-xs text-stone-500">Used for customer card styling</p>
                            <x-input-error :messages="$errors->get('brand_color')" class="mt-2" />
                            <script>
                                document.getElementById('brand_color').addEventListener('input', function(e) {
                                    document.getElementById('brand_color_text').value = e.target.value;
                                });
                                document.getElementById('brand_color_text').addEventListener('input', function(e) {
                                    if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) {
                                        document.getElementById('brand_color').value = e.target.value;
                                    }
                                });
                            </script>
                        </div>

                        <!-- Background Color -->
                        <div class="mb-5">
                            <label for="background_color" class="block mb-2 text-sm font-medium text-stone-700">Background Color</label>
                            <div class="flex gap-2">
                                <input type="color" id="background_color" name="background_color" value="{{ old('background_color', $store->background_color ?? '#1F2937') }}" class="h-10 w-20 rounded border border-stone-300 cursor-pointer">
                                <x-ui.input type="text" id="background_color_text" value="{{ old('background_color', $store->background_color ?? '#1F2937') }}" placeholder="#1F2937" pattern="^#[0-9A-Fa-f]{6}$" class="flex-1" />
                            </div>
                            <p class="mt-1 text-xs text-stone-500">Used for customer card page background</p>
                            <x-input-error :messages="$errors->get('background_color')" class="mt-2" />
                            <script>
                                document.getElementById('background_color').addEventListener('input', function(e) {
                                    document.getElementById('background_color_text').value = e.target.value;
                                });
                                document.getElementById('background_color_text').addEventListener('input', function(e) {
                                    if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) {
                                        document.getElementById('background_color').value = e.target.value;
                                    }
                                });
                            </script>
                        </div>

                        <!-- Logo Upload -->
                        <div class="mb-5">
                            <label for="logo" class="block mb-2 text-sm font-medium text-stone-700">Store Logo</label>
                            @if($store->logo_path)
                                <div class="mb-2">
                                    <p class="text-xs text-stone-500 mb-1">Current logo:</p>
                                    <img src="{{ asset('storage/' . $store->logo_path) }}" alt="Store logo" class="h-16 w-16 object-contain rounded border border-stone-300">
                                </div>
                            @endif
                            <x-ui.input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/jpg,image/webp" />
                            <p class="mt-1 text-xs text-stone-500">PNG, JPG, or WebP (max 2MB). Used for customer card page.</p>
                            <x-input-error :messages="$errors->get('logo')" class="mt-2" />
                        </div>

                        <!-- Pass Logo Upload -->
                        <div class="mb-5">
                            <label for="pass_logo" class="block mb-2 text-sm font-medium text-stone-700">Pass Logo (Wallet Passes)</label>
                            @if($store->pass_logo_path)
                                <div class="mb-2">
                                    <p class="text-xs text-stone-500 mb-1">Current pass logo:</p>
                                    <img src="{{ asset('storage/' . $store->pass_logo_path) }}" alt="Pass logo" class="h-16 w-16 object-contain rounded border border-stone-300">
                                </div>
                            @endif
                            <x-ui.input type="file" id="pass_logo" name="pass_logo" accept="image/png,image/jpeg,image/jpg,image/webp" />
                            <p class="mt-1 text-xs text-stone-500">PNG, JPG, or WebP (max 2MB). Used for Apple Wallet and Google Wallet passes. Recommended: 160x50px.</p>
                            <x-input-error :messages="$errors->get('pass_logo')" class="mt-2" />
                        </div>

                        <!-- Pass Hero Image Upload -->
                        <div class="mb-5">
                            <label for="pass_hero_image" class="block mb-2 text-sm font-medium text-stone-700">Pass Hero Image (Wallet Passes)</label>
                            @if($store->pass_hero_image_path)
                                <div class="mb-2">
                                    <p class="text-xs text-stone-500 mb-1">Current hero image:</p>
                                    <img src="{{ asset('storage/' . $store->pass_hero_image_path) }}" alt="Pass hero image" class="h-32 w-full object-cover rounded border border-stone-300">
                                </div>
                            @endif
                            <x-ui.input type="file" id="pass_hero_image" name="pass_hero_image" accept="image/png,image/jpeg,image/jpg,image/webp" />
                            <p class="mt-1 text-xs text-stone-500">PNG, JPG, or WebP (max 2MB). Banner image for wallet passes. Recommended: 640x180px (Apple Wallet) or 640x200px (Google Wallet).</p>
                            <x-input-error :messages="$errors->get('pass_hero_image')" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end gap-4">
                            <x-ui.button type="submit" variant="primary">
                                Update Store
                            </x-ui.button>
                        </div>
                    </form>

                    <div class="mt-8 pt-6 border-t border-stone-200">
                        <form method="POST" action="{{ route('merchant.stores.destroy', $store) }}" onsubmit="return confirm('Are you sure you want to delete this store?');">
                            @csrf
                            @method('DELETE')
                            <x-ui.button type="submit" variant="danger">
                                Delete Store
                            </x-ui.button>
                        </form>
                    </div>
                </x-ui.card>
    </div>
</x-merchant-layout>

