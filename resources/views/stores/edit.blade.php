<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Store') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('merchant.stores.update', $store) }}" enctype="multipart/form-data" class="max-w-md mx-auto">
                        @csrf
                        @method('PUT')

                        <!-- Name -->
                        <div class="mb-5">
                            <label for="name" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Store Name</label>
                            <input type="text" id="name" name="name" value="{{ old('name', $store->name) }}" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" required>
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <!-- Address -->
                        <div class="mb-5">
                            <label for="address" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Address (Optional)</label>
                            <input type="text" id="address" name="address" value="{{ old('address', $store->address) }}" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                            <x-input-error :messages="$errors->get('address')" class="mt-2" />
                        </div>

                        <!-- Reward Target -->
                        <div class="mb-5">
                            <label for="reward_target" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Stamps needed for reward</label>
                            <input type="number" id="reward_target" name="reward_target" value="{{ old('reward_target', $store->reward_target) }}" min="1" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" required>
                            <x-input-error :messages="$errors->get('reward_target')" class="mt-2" />
                        </div>

                        <!-- Reward Title -->
                        <div class="mb-5">
                            <label for="reward_title" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Reward Title</label>
                            <input type="text" id="reward_title" name="reward_title" value="{{ old('reward_title', $store->reward_title) }}" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" required>
                            <x-input-error :messages="$errors->get('reward_title')" class="mt-2" />
                        </div>

                        <!-- Brand Color -->
                        <div class="mb-5">
                            <label for="brand_color" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Brand Color</label>
                            <div class="flex gap-2">
                                <input type="color" id="brand_color" name="brand_color" value="{{ old('brand_color', $store->brand_color ?? '#0EA5E9') }}" class="h-10 w-20 rounded border border-gray-300 cursor-pointer">
                                <input type="text" id="brand_color_text" value="{{ old('brand_color', $store->brand_color ?? '#0EA5E9') }}" placeholder="#0EA5E9" pattern="^#[0-9A-Fa-f]{6}$" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block flex-1 p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Used for customer card styling</p>
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
                            <label for="background_color" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Background Color</label>
                            <div class="flex gap-2">
                                <input type="color" id="background_color" name="background_color" value="{{ old('background_color', $store->background_color ?? '#1F2937') }}" class="h-10 w-20 rounded border border-gray-300 cursor-pointer">
                                <input type="text" id="background_color_text" value="{{ old('background_color', $store->background_color ?? '#1F2937') }}" placeholder="#1F2937" pattern="^#[0-9A-Fa-f]{6}$" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block flex-1 p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Used for customer card page background</p>
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
                            <label for="logo" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Store Logo</label>
                            @if($store->logo_path)
                                <div class="mb-2">
                                    <p class="text-xs text-gray-500 mb-1">Current logo:</p>
                                    <img src="{{ asset('storage/' . $store->logo_path) }}" alt="Store logo" class="h-16 w-16 object-contain rounded border border-gray-300">
                                </div>
                            @endif
                            <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/jpg,image/webp" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                            <p class="mt-1 text-xs text-gray-500">PNG, JPG, or WebP (max 2MB). Used for customer card page.</p>
                            <x-input-error :messages="$errors->get('logo')" class="mt-2" />
                        </div>

                        <!-- Pass Logo Upload -->
                        <div class="mb-5">
                            <label for="pass_logo" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Pass Logo (Wallet Passes)</label>
                            @if($store->pass_logo_path)
                                <div class="mb-2">
                                    <p class="text-xs text-gray-500 mb-1">Current pass logo:</p>
                                    <img src="{{ asset('storage/' . $store->pass_logo_path) }}" alt="Pass logo" class="h-16 w-16 object-contain rounded border border-gray-300">
                                </div>
                            @endif
                            <input type="file" id="pass_logo" name="pass_logo" accept="image/png,image/jpeg,image/jpg,image/webp" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                            <p class="mt-1 text-xs text-gray-500">PNG, JPG, or WebP (max 2MB). Used for Apple Wallet and Google Wallet passes. Recommended: 160x50px.</p>
                            <x-input-error :messages="$errors->get('pass_logo')" class="mt-2" />
                        </div>

                        <!-- Pass Hero Image Upload -->
                        <div class="mb-5">
                            <label for="pass_hero_image" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Pass Hero Image (Wallet Passes)</label>
                            @if($store->pass_hero_image_path)
                                <div class="mb-2">
                                    <p class="text-xs text-gray-500 mb-1">Current hero image:</p>
                                    <img src="{{ asset('storage/' . $store->pass_hero_image_path) }}" alt="Pass hero image" class="h-32 w-full object-cover rounded border border-gray-300">
                                </div>
                            @endif
                            <input type="file" id="pass_hero_image" name="pass_hero_image" accept="image/png,image/jpeg,image/jpg,image/webp" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                            <p class="mt-1 text-xs text-gray-500">PNG, JPG, or WebP (max 2MB). Banner image for wallet passes. Recommended: 640x180px (Apple Wallet) or 640x200px (Google Wallet).</p>
                            <x-input-error :messages="$errors->get('pass_hero_image')" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-between">
                            <button type="submit" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm w-full sm:w-auto px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">Update Store</button>
                        </div>
                    </form>

                     <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700 max-w-md mx-auto">
                        <form method="POST" action="{{ route('merchant.stores.destroy', $store) }}" onsubmit="return confirm('Are you sure you want to delete this store?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-700 hover:text-white border border-red-700 hover:bg-red-800 focus:ring-4 focus:outline-none focus:ring-red-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:border-red-500 dark:text-red-500 dark:hover:text-white dark:hover:bg-red-600 dark:focus:ring-red-900">Delete Store</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

