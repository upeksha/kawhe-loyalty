<x-merchant-layout>
    <x-slot name="header">
        {{ __('Create Store') }}
    </x-slot>

    <div class="max-w-2xl mx-auto">
        <x-ui.card class="p-6">
            <form method="POST" action="{{ route('merchant.stores.store') }}" enctype="multipart/form-data">
                @csrf

                <!-- Name -->
                <div class="mb-5">
                    <label for="name" class="block mb-2 text-sm font-medium text-stone-700">Store Name</label>
                    <x-ui.input type="text" id="name" name="name" value="{{ old('name') }}" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <!-- Address -->
                <div class="mb-5">
                    <label for="address" class="block mb-2 text-sm font-medium text-stone-700">Address (Optional)</label>
                    <x-ui.input type="text" id="address" name="address" value="{{ old('address') }}" />
                    <x-input-error :messages="$errors->get('address')" class="mt-2" />
                </div>

                <!-- Reward Target -->
                <div class="mb-5">
                    <label for="reward_target" class="block mb-2 text-sm font-medium text-stone-700">Stamps needed for reward</label>
                    <x-ui.input type="number" id="reward_target" name="reward_target" value="{{ old('reward_target', 9) }}" min="1" required />
                    <x-input-error :messages="$errors->get('reward_target')" class="mt-2" />
                </div>

                <!-- Reward Title -->
                <div class="mb-5">
                    <label for="reward_title" class="block mb-2 text-sm font-medium text-stone-700">Reward Title</label>
                    <x-ui.input type="text" id="reward_title" name="reward_title" value="{{ old('reward_title', 'Free coffee') }}" required />
                    <x-input-error :messages="$errors->get('reward_title')" class="mt-2" />
                </div>

                <!-- Brand Color -->
                <div class="mb-5">
                    <label for="brand_color" class="block mb-2 text-sm font-medium text-stone-700">Brand Color (Optional)</label>
                    <div class="flex gap-2">
                        <input type="color" id="brand_color" name="brand_color" value="{{ old('brand_color', '#0EA5E9') }}" class="h-10 w-20 rounded border border-stone-300 cursor-pointer">
                        <x-ui.input type="text" id="brand_color_text" value="{{ old('brand_color', '#0EA5E9') }}" placeholder="#0EA5E9" pattern="^#[0-9A-Fa-f]{6}$" class="flex-1" />
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
                    <label for="background_color" class="block mb-2 text-sm font-medium text-stone-700">Background Color (Optional)</label>
                    <div class="flex gap-2">
                        <input type="color" id="background_color" name="background_color" value="{{ old('background_color', '#1F2937') }}" class="h-10 w-20 rounded border border-stone-300 cursor-pointer">
                        <x-ui.input type="text" id="background_color_text" value="{{ old('background_color', '#1F2937') }}" placeholder="#1F2937" pattern="^#[0-9A-Fa-f]{6}$" class="flex-1" />
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
                    <label for="logo" class="block mb-2 text-sm font-medium text-stone-700">Store Logo (Optional)</label>
                    <x-ui.input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/jpg,image/webp" />
                    <p class="mt-1 text-xs text-stone-500">PNG, JPG, or WebP (max 2MB). Used for customer card page.</p>
                    <x-input-error :messages="$errors->get('logo')" class="mt-2" />
                </div>

                <!-- Pass Logo Upload -->
                <div class="mb-5">
                    <label for="pass_logo" class="block mb-2 text-sm font-medium text-stone-700">Pass Logo (Wallet Passes) (Optional)</label>
                    <x-ui.input type="file" id="pass_logo" name="pass_logo" accept="image/png,image/jpeg,image/jpg,image/webp" />
                    <p class="mt-1 text-xs text-stone-500">PNG, JPG, or WebP (max 2MB). Used for Apple Wallet and Google Wallet passes. Recommended: 160x50px.</p>
                    <x-input-error :messages="$errors->get('pass_logo')" class="mt-2" />
                </div>

                <!-- Pass Hero Image Upload -->
                <div class="mb-5">
                    <label for="pass_hero_image" class="block mb-2 text-sm font-medium text-stone-700">Pass Hero Image (Wallet Passes) (Optional)</label>
                    <x-ui.input type="file" id="pass_hero_image" name="pass_hero_image" accept="image/png,image/jpeg,image/jpg,image/webp" />
                    <p class="mt-1 text-xs text-stone-500">PNG, JPG, or WebP (max 2MB). Banner image for wallet passes. Recommended: 640x180px (Apple Wallet) or 640x200px (Google Wallet).</p>
                    <x-input-error :messages="$errors->get('pass_hero_image')" class="mt-2" />
                </div>

                <div class="flex items-center justify-end">
                    <x-ui.button type="submit" variant="primary">
                        Create Store
                    </x-ui.button>
                </div>
            </form>
                </x-ui.card>
    </div>
</x-merchant-layout>

