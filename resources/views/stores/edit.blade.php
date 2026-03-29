<x-merchant-layout>
    <x-slot name="header">
        {{ __('Edit Store') }}
    </x-slot>

    @php
        $usageStats = $usageStats ?? app(\App\Services\Billing\UsageService::class)->getUsageStats(request()->user());
        $walletReady = !empty($store->reward_title)
            && (int) ($store->reward_target ?? 0) > 0
            && !empty($store->background_color)
            && (!empty($store->logo_path) || !empty($store->pass_logo_path));
        $billingReady = isset($usageStats)
            ? (bool) ($usageStats['can_create_card'] ?? false)
            : true;
        $launchChecks = [
            !empty($store->reward_title) && (int) ($store->reward_target ?? 0) > 0,
            !empty($store->brand_color) && !empty($store->background_color),
            !empty($store->logo_path),
            !empty($store->pass_logo_path) || !empty($store->pass_hero_image_path),
            $billingReady,
        ];
        $launchScore = collect($launchChecks)->filter()->count();
        $launchLabel = $launchScore >= 5
            ? 'Good to launch'
            : ($launchScore >= 3 ? 'Launchable, but could be improved' : 'Needs review');
        $launchTone = $launchScore >= 5
            ? 'bg-emerald-100 text-emerald-700'
            : ($launchScore >= 3 ? 'bg-amber-100 text-amber-700' : 'bg-accent-100 text-accent-700');
    @endphp

    <div class="max-w-5xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
        <x-ui.card class="p-6">
                    @if($store->trashed())
                        <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-sm font-semibold text-amber-800">This store is archived</p>
                                    <p class="mt-1 text-sm leading-relaxed text-amber-700">
                                        New joins are paused, QR sharing is disabled, and customers can no longer stamp or redeem through this store until you restore it. Customer records, wallet history, and branding are still preserved.
                                    </p>
                                </div>
                                <form method="POST" action="{{ route('merchant.stores.restore', $store) }}">
                                    @csrf
                                    <x-ui.button type="submit" variant="secondary" size="sm">
                                        Restore Store
                                    </x-ui.button>
                                </form>
                            </div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('merchant.stores.update', $store) }}" enctype="multipart/form-data" class="max-w-md mx-auto" id="store-edit-form">
                        @csrf
                        @method('PUT')
                        <x-form-error-summary form-id="store-edit-form" />

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
                            <div class="flex flex-wrap items-start gap-3">
                                @if($store->logo_path)
                                    <div class="mb-2">
                                        <p class="text-xs text-stone-500 mb-1">Current:</p>
                                        <img src="{{ $store->logo_url }}" alt="Store logo" class="h-20 w-20 object-contain rounded-lg border border-stone-300 shadow-sm">
                                    </div>
                                @endif
                                <div id="logo-thumbnail" class="hidden">
                                    <p class="text-xs text-stone-500 mb-1">New selection:</p>
                                    <img id="logo-thumbnail-img" src="" alt="Preview" class="h-20 w-20 object-contain rounded-lg border border-stone-300 bg-white shadow-sm">
                                </div>
                                <div class="flex-1 min-w-0">
                                    <x-ui.input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/jpg,image/webp" />
                                </div>
                            </div>
                            <p class="mt-1 text-xs text-stone-500">PNG, JPG, or WebP (max 2MB). Used for customer card page.</p>
                            <x-input-error :messages="$errors->get('logo')" class="mt-2" />
                        </div>

                        <!-- Pass Logo Upload -->
                        <div class="mb-5">
                            <label for="pass_logo" class="block mb-2 text-sm font-medium text-stone-700">Pass Logo (Wallet Passes)</label>
                            <div class="flex flex-wrap items-start gap-3">
                                @if($store->pass_logo_path)
                                    <div class="mb-2">
                                        <p class="text-xs text-stone-500 mb-1">Current:</p>
                                        <img src="{{ $store->pass_logo_url }}" alt="Pass logo" class="h-12 w-20 object-contain rounded-lg border border-stone-300 shadow-sm">
                                    </div>
                                @endif
                                <div id="pass_logo-thumbnail" class="hidden">
                                    <p class="text-xs text-stone-500 mb-1">New selection:</p>
                                    <img id="pass_logo-thumbnail-img" src="" alt="Preview" class="h-12 w-20 object-contain rounded-lg border border-stone-300 bg-white shadow-sm">
                                </div>
                                <div class="flex-1 min-w-0">
                                    <x-ui.input type="file" id="pass_logo" name="pass_logo" accept="image/png,image/jpeg,image/jpg,image/webp" />
                                </div>
                            </div>
                            <p class="mt-1 text-xs text-stone-500">PNG, JPG, or WebP (max 2MB). Recommended: 160x50px.</p>
                            <x-input-error :messages="$errors->get('pass_logo')" class="mt-2" />
                        </div>

                        <!-- Pass Hero Image Upload -->
                        <div class="mb-5">
                            <label for="pass_hero_image" class="block mb-2 text-sm font-medium text-stone-700">Pass Hero Image (Wallet Passes)</label>
                            <div class="flex flex-wrap items-start gap-3">
                                @if($store->pass_hero_image_path)
                                    <div class="mb-2">
                                        <p class="text-xs text-stone-500 mb-1">Current:</p>
                                        <img src="{{ $store->pass_hero_image_url }}" alt="Pass hero" class="h-20 w-32 object-cover rounded-lg border border-stone-300 shadow-sm">
                                    </div>
                                @endif
                                <div id="pass_hero_image-thumbnail" class="hidden">
                                    <p class="text-xs text-stone-500 mb-1">New selection:</p>
                                    <img id="pass_hero_image-thumbnail-img" src="" alt="Preview" class="h-20 w-32 object-cover rounded-lg border border-stone-300 shadow-sm">
                                </div>
                                <div class="flex-1 min-w-0">
                                    <x-ui.input type="file" id="pass_hero_image" name="pass_hero_image" accept="image/png,image/jpeg,image/jpg,image/webp" />
                                </div>
                            </div>
                            <p class="mt-1 text-xs text-stone-500">PNG, JPG, or WebP (max 2MB). Recommended: 640x180px or 640x200px.</p>
                            <x-input-error :messages="$errors->get('pass_hero_image')" class="mt-2" />
                        </div>

                        <script>
                            document.getElementById('logo')?.addEventListener('change', function(e) {
                                var container = document.getElementById('logo-thumbnail');
                                var img = document.getElementById('logo-thumbnail-img');
                                if (e.target.files?.[0]) {
                                    img.src = URL.createObjectURL(e.target.files[0]);
                                    container.classList.remove('hidden');
                                } else {
                                    img.src = '';
                                    container.classList.add('hidden');
                                }
                            });
                            document.getElementById('pass_logo')?.addEventListener('change', function(e) {
                                var container = document.getElementById('pass_logo-thumbnail');
                                var img = document.getElementById('pass_logo-thumbnail-img');
                                if (e.target.files?.[0]) {
                                    img.src = URL.createObjectURL(e.target.files[0]);
                                    container.classList.remove('hidden');
                                } else {
                                    img.src = '';
                                    container.classList.add('hidden');
                                }
                            });
                            document.getElementById('pass_hero_image')?.addEventListener('change', function(e) {
                                var container = document.getElementById('pass_hero_image-thumbnail');
                                var img = document.getElementById('pass_hero_image-thumbnail-img');
                                if (e.target.files?.[0]) {
                                    img.src = URL.createObjectURL(e.target.files[0]);
                                    container.classList.remove('hidden');
                                } else {
                                    img.src = '';
                                    container.classList.add('hidden');
                                }
                            });
                        </script>

                        <!-- Customer Registration Form Fields -->
                        @php
                            $formConfig = $store->registration_form_config ?? [];
                            $fields = [
                                'first_name' => 'First Name',
                                'last_name'  => 'Last Name',
                                'phone'      => 'Phone Number',
                                'birthday'   => 'Birthday',
                            ];
                        @endphp
                        <div class="mb-5">
                            <label class="block mb-2 text-sm font-medium text-stone-700">Customer Registration Fields</label>
                            <p class="text-xs text-stone-500 mb-3">Choose which fields to collect when customers join your loyalty program. Email is always required.</p>
                            <div class="space-y-3 rounded-xl border border-stone-200 p-4 bg-stone-50">
                                <!-- Email — always on, locked -->
                                <div class="flex items-center justify-between py-1">
                                    <span class="text-sm font-medium text-stone-700">Email Address</span>
                                    <div class="flex items-center gap-4">
                                        <label class="flex items-center gap-1.5 text-xs text-stone-400 cursor-not-allowed">
                                            <input type="checkbox" checked disabled class="w-4 h-4 rounded text-brand-600 border-stone-300 opacity-50">
                                            Enabled
                                        </label>
                                        <label class="flex items-center gap-1.5 text-xs text-stone-400 cursor-not-allowed">
                                            <input type="checkbox" checked disabled class="w-4 h-4 rounded text-brand-600 border-stone-300 opacity-50">
                                            Required
                                        </label>
                                    </div>
                                </div>
                                @foreach($fields as $key => $label)
                                    @php
                                        $enabled  = old("{$key}_enabled",  ($formConfig[$key]['enabled']  ?? false) ? '1' : '0') === '1';
                                        $required = old("{$key}_required", ($formConfig[$key]['required'] ?? false) ? '1' : '0') === '1';
                                    @endphp
                                    <div class="flex items-center justify-between py-1 border-t border-stone-200" x-data="{ enabled: {{ $enabled ? 'true' : 'false' }} }">
                                        <span class="text-sm font-medium text-stone-700">{{ $label }}</span>
                                        <div class="flex items-center gap-4">
                                            <label class="flex items-center gap-1.5 text-xs text-stone-600 cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    name="{{ $key }}_enabled"
                                                    value="1"
                                                    x-model="enabled"
                                                    {{ $enabled ? 'checked' : '' }}
                                                    class="w-4 h-4 rounded text-brand-600 border-stone-300 focus:ring-brand-500"
                                                >
                                                Enabled
                                            </label>
                                            <label class="flex items-center gap-1.5 text-xs cursor-pointer" :class="enabled ? 'text-stone-600' : 'text-stone-300 cursor-not-allowed'">
                                                <input
                                                    type="checkbox"
                                                    name="{{ $key }}_required"
                                                    value="1"
                                                    :disabled="!enabled"
                                                    {{ $required ? 'checked' : '' }}
                                                    class="w-4 h-4 rounded text-brand-600 border-stone-300 focus:ring-brand-500 disabled:opacity-40"
                                                >
                                                Required
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-4">
                            <x-ui.button type="submit" variant="primary">
                                Update Store
                            </x-ui.button>
                        </div>
                    </form>

                    <div class="mt-8 pt-6 border-t border-stone-200">
                        <div class="rounded-2xl border border-accent-200 bg-accent-50 p-4">
                            <p class="text-sm font-semibold text-accent-800">{{ $store->trashed() ? 'Archived store' : 'Archive this store' }}</p>
                            <ul class="mt-2 space-y-1 text-sm leading-relaxed text-accent-700 list-disc list-inside">
                                <li>Join links and QR codes stop accepting new customers.</li>
                                <li>Existing customer cards and history stay preserved for support and restore.</li>
                                <li>Wallet cards may remain on customer phones, but new stamp or redeem actions should stop.</li>
                            </ul>
                            <div class="mt-4 flex flex-wrap gap-2">
                                @if($store->trashed())
                                    <form method="POST" action="{{ route('merchant.stores.restore', $store) }}">
                                        @csrf
                                        <x-ui.button type="submit" variant="secondary">
                                            Restore Store
                                        </x-ui.button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('merchant.stores.destroy', $store) }}" onsubmit="return confirm('Archive this store? Customers and history will stay preserved, but new joins and QR sharing will stop until you restore it.');">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button type="submit" variant="danger">
                                            Archive Store
                                        </x-ui.button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </x-ui.card>
        </div>

        <div class="lg:col-span-1 space-y-4">
            <x-ui.card class="p-5 mb-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-bold text-stone-900">Wallet Health</h3>
                        <p class="mt-1 text-sm text-stone-600">Use this before launch or when a merchant reports a stale pass.</p>
                    </div>
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $walletHealth['status_tone'] }}">
                        {{ $walletHealth['status_label'] }}
                    </span>
                </div>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-stone-200 bg-stone-50/70 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">Active cards</p>
                        <p class="mt-1 text-lg font-semibold text-stone-900">{{ $walletHealth['active_cards'] }}</p>
                    </div>
                    <div class="rounded-xl border border-stone-200 bg-stone-50/70 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">Wallet issues (7 days)</p>
                        <p class="mt-1 text-lg font-semibold text-stone-900">{{ $walletHealth['wallet_failures_last_7_days'] }}</p>
                    </div>
                </div>
                <div class="mt-4 rounded-xl border border-stone-200 bg-white p-4">
                    <p class="text-sm font-semibold text-stone-800">Recommended next action</p>
                    <p class="mt-2 text-sm leading-relaxed text-stone-600">{{ $walletHealth['recommended_action'] }}</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @unless($store->trashed())
                            <form method="POST" action="{{ route('merchant.stores.refresh-wallets', $store) }}">
                                @csrf
                                <x-ui.button type="submit" variant="secondary" size="sm">
                                    Queue Wallet Refresh for All Cards
                                </x-ui.button>
                            </form>
                        @endunless
                        <x-ui.button href="{{ route('merchant.support.index', ['event_type' => 'wallet_sync', 'store_id' => $store->id]) }}" variant="ghost" size="sm">
                            Review Wallet Logs
                        </x-ui.button>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card class="p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-bold text-stone-900">Launch Quality</h3>
                        <p class="mt-1 text-sm text-stone-600">A quick overall read on whether this store feels ready to share publicly.</p>
                    </div>
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $launchTone }}">
                        {{ $launchLabel }}
                    </span>
                </div>
                <p class="mt-4 text-sm text-stone-600">{{ $launchScore }}/5 launch signals are in a strong place.</p>
                <p class="mt-3 text-xs leading-relaxed text-stone-500">This score is guidance only. It helps merchants catch branding or plan issues before they print posters or publish the join link.</p>
            </x-ui.card>

            <x-ui.card class="p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-bold text-stone-900">Wallet Readiness</h3>
                        <p class="mt-1 text-sm text-stone-600">Check whether this store is visually ready for Apple Wallet and Google Wallet.</p>
                    </div>
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $walletReady ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                        {{ $walletReady ? 'Ready' : 'Needs review' }}
                    </span>
                </div>

                <ul class="mt-4 space-y-3 text-sm">
                    <li class="flex items-center justify-between rounded-xl border border-stone-200 bg-stone-50/80 px-4 py-3">
                        <span class="text-stone-700">Reward setup</span>
                        <span class="font-medium {{ !empty($store->reward_title) && (int) ($store->reward_target ?? 0) > 0 ? 'text-emerald-700' : 'text-stone-500' }}">
                            {{ !empty($store->reward_title) && (int) ($store->reward_target ?? 0) > 0 ? 'Ready' : 'Needed' }}
                        </span>
                    </li>
                    <li class="flex items-center justify-between rounded-xl border border-stone-200 bg-stone-50/80 px-4 py-3">
                        <span class="text-stone-700">Store branding</span>
                        <span class="font-medium {{ !empty($store->logo_path) ? 'text-emerald-700' : 'text-amber-700' }}">
                            {{ !empty($store->logo_path) ? 'Ready' : 'Optional' }}
                        </span>
                    </li>
                    <li class="flex items-center justify-between rounded-xl border border-stone-200 bg-stone-50/80 px-4 py-3">
                        <span class="text-stone-700">Wallet assets</span>
                        <span class="font-medium {{ (!empty($store->pass_logo_path) || !empty($store->pass_hero_image_path)) ? 'text-emerald-700' : 'text-amber-700' }}">
                            {{ (!empty($store->pass_logo_path) || !empty($store->pass_hero_image_path)) ? 'Ready' : 'Optional' }}
                        </span>
                    </li>
                    <li class="flex items-center justify-between rounded-xl border border-stone-200 bg-stone-50/80 px-4 py-3">
                        <span class="text-stone-700">Background styling</span>
                        <span class="font-medium {{ !empty($store->background_color) ? 'text-emerald-700' : 'text-stone-500' }}">
                            {{ !empty($store->background_color) ? 'Ready' : 'Needed' }}
                        </span>
                    </li>
                </ul>
            </x-ui.card>

            <x-ui.card class="p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-bold text-stone-900">Billing Readiness</h3>
                        <p class="mt-1 text-sm text-stone-600">Check that plan limits will not block new customer signups for this store.</p>
                    </div>
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $billingReady ? 'bg-emerald-100 text-emerald-700' : 'bg-accent-100 text-accent-700' }}">
                        {{ $billingReady ? 'Ready' : 'Review plan' }}
                    </span>
                </div>

                <ul class="mt-4 space-y-3 text-sm">
                    <li class="flex items-center justify-between rounded-xl border border-stone-200 bg-stone-50/80 px-4 py-3">
                        <span class="text-stone-700">Can add more cards</span>
                        <span class="font-medium {{ $billingReady ? 'text-emerald-700' : 'text-accent-700' }}">
                            {{ $billingReady ? 'Yes' : 'No' }}
                        </span>
                    </li>
                    <li class="flex items-center justify-between rounded-xl border border-stone-200 bg-stone-50/80 px-4 py-3">
                        <span class="text-stone-700">Verification policy</span>
                        <span class="font-medium text-stone-900">
                            {{ ($store->require_verification_for_redemption ?? true) ? 'Protected' : 'Open' }}
                        </span>
                    </li>
                </ul>

                <div class="mt-4 flex flex-wrap gap-2">
                    <x-ui.button href="{{ route('billing.index') }}" variant="secondary" size="sm">
                        Open Billing
                    </x-ui.button>
                    @if(!$store->trashed())
                        <x-ui.button href="{{ route('merchant.stores.qr', $store) }}" variant="ghost" size="sm">
                            Open QR page
                        </x-ui.button>
                    @endif
                </div>
            </x-ui.card>

            <x-ui.card class="p-5">
                <h3 class="text-base font-bold text-stone-900">Asset guidance</h3>
                <ul class="mt-4 space-y-3 text-sm leading-relaxed text-stone-600">
                    <li>Use a clean logo with space around it so it still reads at wallet size.</li>
                    <li>Choose one strong hero image rather than a busy collage with small text.</li>
                    <li>If you only upload one thing, make it the store logo. It improves the most screens at once.</li>
                </ul>
            </x-ui.card>
        </div>
    </div>
</x-merchant-layout>
