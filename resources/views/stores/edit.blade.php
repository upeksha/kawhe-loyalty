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
            ? (bool) ($usageStats['can_create_program'] ?? false)
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

    <div class="mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6">
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

                    <form
                        method="POST"
                        action="{{ route('merchant.stores.update', $store) }}"
                        enctype="multipart/form-data"
                        class="max-w-md mx-auto"
                        id="store-edit-form"
                        x-data="{
                            brandColor: @js(old('brand_color', $store->brand_color ?? '#0EA5E9')),
                            bgColor: @js(old('background_color', $store->background_color ?? '#1F2937')),
                            hexToRgb(hex) {
                                const cleaned = (hex || '').replace('#', '');
                                if (cleaned.length !== 6) return null;
                                const value = parseInt(cleaned, 16);
                                if (Number.isNaN(value)) return null;
                                return { r: (value >> 16) & 255, g: (value >> 8) & 255, b: value & 255 };
                            },
                            luminance(hex) {
                                const rgb = this.hexToRgb(hex);
                                if (!rgb) return 0;
                                const convert = (channel) => {
                                    const normalized = channel / 255;
                                    return normalized <= 0.03928
                                        ? normalized / 12.92
                                        : Math.pow((normalized + 0.055) / 1.055, 2.4);
                                };
                                return (0.2126 * convert(rgb.r)) + (0.7152 * convert(rgb.g)) + (0.0722 * convert(rgb.b));
                            },
                            contrastRatio(a, b) {
                                const l1 = this.luminance(a);
                                const l2 = this.luminance(b);
                                const lighter = Math.max(l1, l2);
                                const darker = Math.min(l1, l2);
                                return (lighter + 0.05) / (darker + 0.05);
                            },
                            get hasLowContrastPreview() {
                                return this.contrastRatio(this.brandColor, this.bgColor) < 3;
                            },
                            get hasBlockedContrast() {
                                return this.contrastRatio(this.brandColor, this.bgColor) < 2;
                            },
                            get hasVeryLightBackground() {
                                return this.luminance(this.bgColor) > 0.9;
                            }
                        }"
                        x-init="
                            $refs.brandColor.addEventListener('input', e => { brandColor = e.target.value; $refs.brandColorText.value = e.target.value; });
                            $refs.brandColorText.addEventListener('input', e => { if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) { brandColor = e.target.value; $refs.brandColor.value = e.target.value; } });
                            $refs.bgColor.addEventListener('input', e => { bgColor = e.target.value; $refs.bgColorText.value = e.target.value; });
                            $refs.bgColorText.addEventListener('input', e => { if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) { bgColor = e.target.value; $refs.bgColor.value = e.target.value; } });
                        "
                    >
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

                        <div class="mb-6 rounded-2xl border border-stone-200 bg-stone-50/70 p-4">
                            <p class="text-sm font-semibold text-stone-900">Default loyalty card</p>
                            <p class="mt-1 text-sm text-stone-600">Reward rules, verification, and join form fields are managed from the default card screen.</p>
                            @if($defaultProgram)
                                @php
                                    $defaultFormConfig = $defaultProgram->registration_form_config ?? [];
                                    $enabledJoinFields = collect(['first_name', 'last_name', 'phone', 'birthday'])
                                        ->filter(fn ($field) => data_get($defaultFormConfig, "{$field}.enabled"))
                                        ->map(fn ($field) => str($field)->replace('_', ' ')->title())
                                        ->values();
                                @endphp
                                <div class="mt-4 rounded-2xl border border-stone-200 bg-white p-4">
                                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="space-y-2">
                                            <p class="font-semibold text-stone-900">{{ $defaultProgram->name }}</p>
                                            <p class="text-sm text-stone-600">{{ $defaultProgram->reward_target }} stamps for {{ $defaultProgram->reward_title }}</p>
                                            <p class="text-xs text-stone-500">
                                                Redemption verification: {{ $defaultProgram->require_verification_for_redemption ? 'Required' : 'Optional' }}
                                            </p>
                                            <p class="text-xs text-stone-500">
                                                Join fields:
                                                {{ $enabledJoinFields->isNotEmpty() ? $enabledJoinFields->join(', ') : 'Email only' }}
                                            </p>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <x-ui.button href="{{ route('merchant.stores.programs.edit', [$store, $defaultProgram]) }}" variant="secondary" size="sm">
                                                Edit Default Card
                                            </x-ui.button>
                                            <x-ui.button href="{{ route('merchant.stores.programs.index', $store) }}" variant="ghost" size="sm">
                                                View All Cards
                                            </x-ui.button>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="mb-5">
                            <x-input-error :messages="$errors->get('reward_target')" class="mt-2" />
                            <x-input-error :messages="$errors->get('reward_title')" class="mt-2" />
                            <x-input-error :messages="$errors->get('require_verification_for_redemption')" class="mt-2" />
                        </div>
                        
                        <!-- Brand Color -->
                        <div class="mb-5">
                            <label for="brand_color" class="block mb-2 text-sm font-medium text-stone-700">Brand Color</label>
                            <div class="flex gap-2">
                                <input type="color" id="brand_color" name="brand_color" x-ref="brandColor" value="{{ old('brand_color', $store->brand_color ?? '#0EA5E9') }}" class="color-swatch-input h-11 w-11 rounded-full border-2 cursor-pointer flex-shrink-0 overflow-hidden p-0 bg-transparent appearance-none" x-bind:class="hasBlockedContrast ? 'border-red-300' : 'border-stone-300'">
                                <x-ui.input type="text" id="brand_color_text" x-ref="brandColorText" value="{{ old('brand_color', $store->brand_color ?? '#0EA5E9') }}" placeholder="#0EA5E9" pattern="^#[0-9A-Fa-f]{6}$" class="flex-1" x-bind:class="hasBlockedContrast ? '!border-red-300' : ''" />
                            </div>
                            <p class="mt-1 text-xs text-stone-500">Used for customer card styling</p>
                            <x-input-error :messages="$errors->get('brand_color')" class="mt-2" />
                        </div>

                        <!-- Background Color -->
                        <div class="mb-5">
                            <label for="background_color" class="block mb-2 text-sm font-medium text-stone-700">Background Color</label>
                            <div class="flex gap-2">
                                <input type="color" id="background_color" name="background_color" x-ref="bgColor" value="{{ old('background_color', $store->background_color ?? '#1F2937') }}" class="color-swatch-input h-11 w-11 rounded-full border-2 cursor-pointer flex-shrink-0 overflow-hidden p-0 bg-transparent appearance-none" x-bind:class="hasBlockedContrast ? 'border-red-300' : 'border-stone-300'">
                                <x-ui.input type="text" id="background_color_text" x-ref="bgColorText" value="{{ old('background_color', $store->background_color ?? '#1F2937') }}" placeholder="#1F2937" pattern="^#[0-9A-Fa-f]{6}$" class="flex-1" x-bind:class="hasBlockedContrast ? '!border-red-300' : ''" />
                            </div>
                            <p class="mt-1 text-xs text-stone-500">Used for customer card page background</p>
                            <x-input-error :messages="$errors->get('background_color')" class="mt-2" />
                        </div>

                        <div x-show="hasBlockedContrast" x-cloak class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                            <p class="font-semibold">Colors need more contrast</p>
                            <p class="mt-1 leading-relaxed">
                                Your brand and background colors are too close together, so the join page and saved card can become unreadable. Use a darker background, a brighter accent, or increase the contrast between them before saving.
                            </p>
                        </div>

                        <div x-show="!hasBlockedContrast && (hasLowContrastPreview || hasVeryLightBackground)" x-cloak class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            <p class="font-semibold">Preview warning</p>
                            <p class="mt-1 leading-relaxed">
                                This combination will save, but it may still look washed out on the join page or wallet handoff. The safest fix is a darker background and a more distinct accent color.
                            </p>
                        </div>

                        <!-- Logo Upload -->
                        <div class="mb-5">
                            <label for="logo" class="block mb-2 text-sm font-medium text-stone-700">Store Logo</label>
                            <div class="flex flex-wrap items-center gap-4">
                                @if($store->logo_path)
                                    <div class="flex shrink-0 flex-col items-center">
                                        <p class="mb-1 text-xs text-stone-500">Current:</p>
                                        <img src="{{ $store->logo_url }}" alt="Store logo" class="h-20 w-20 rounded-lg border border-stone-300 object-contain shadow-sm">
                                    </div>
                                @endif
                                <div id="logo-thumbnail" class="hidden flex shrink-0 flex-col items-center">
                                    <p class="mb-1 text-xs text-stone-500">New selection:</p>
                                    <img id="logo-thumbnail-img" src="" alt="Preview" class="h-20 w-20 rounded-lg border border-stone-300 bg-white object-contain shadow-sm">
                                </div>
                                <div class="min-w-[12rem] flex-1 self-center">
                                    <x-ui.input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/jpg,image/webp" />
                                </div>
                            </div>
                            <p class="mt-1 text-xs text-stone-500">PNG, JPG, or WebP (max 2MB). Used for customer card page.</p>
                            <x-input-error :messages="$errors->get('logo')" class="mt-2" />
                        </div>

                        <!-- Pass Logo Upload -->
                        <div class="mb-5">
                            <label for="pass_logo" class="block mb-2 text-sm font-medium text-stone-700">Pass Logo (Wallet Passes)</label>
                            <div class="flex flex-wrap items-center gap-4">
                                @if($store->pass_logo_path)
                                    <div class="flex shrink-0 flex-col items-center">
                                        <p class="mb-1 text-xs text-stone-500">Current:</p>
                                        <img src="{{ $store->pass_logo_url }}" alt="Pass logo" class="h-12 w-20 rounded-lg border border-stone-300 object-contain shadow-sm">
                                    </div>
                                @endif
                                <div id="pass_logo-thumbnail" class="hidden flex shrink-0 flex-col items-center">
                                    <p class="mb-1 text-xs text-stone-500">New selection:</p>
                                    <img id="pass_logo-thumbnail-img" src="" alt="Preview" class="h-12 w-20 rounded-lg border border-stone-300 bg-white object-contain shadow-sm">
                                </div>
                                <div class="min-w-[12rem] flex-1 self-center">
                                    <x-ui.input type="file" id="pass_logo" name="pass_logo" accept="image/png,image/jpeg,image/jpg,image/webp" />
                                </div>
                            </div>
                            <p class="mt-1 text-xs text-stone-500">PNG, JPG, or WebP (max 2MB). Recommended: 160x50px.</p>
                            <x-input-error :messages="$errors->get('pass_logo')" class="mt-2" />
                        </div>

                        <!-- Pass Hero Image Upload -->
                        <div class="mb-5">
                            <label for="pass_hero_image" class="block mb-2 text-sm font-medium text-stone-700">Pass Hero Image (Wallet Passes)</label>
                            <div class="flex flex-wrap items-center gap-4">
                                @if($store->pass_hero_image_path)
                                    <div class="flex shrink-0 flex-col items-center">
                                        <p class="mb-1 text-xs text-stone-500">Current:</p>
                                        <img src="{{ $store->pass_hero_image_url }}" alt="Pass hero" class="h-20 w-32 rounded-lg border border-stone-300 object-cover shadow-sm">
                                    </div>
                                @endif
                                <div id="pass_hero_image-thumbnail" class="hidden flex shrink-0 flex-col items-center">
                                    <p class="mb-1 text-xs text-stone-500">New selection:</p>
                                    <img id="pass_hero_image-thumbnail-img" src="" alt="Preview" class="h-20 w-32 rounded-lg border border-stone-300 object-cover shadow-sm">
                                </div>
                                <div class="min-w-[12rem] flex-1 self-center">
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

                        <div class="flex items-center justify-end gap-4">
                            <x-ui.button type="submit" variant="primary" x-bind:disabled="hasBlockedContrast" x-bind:aria-disabled="hasBlockedContrast ? 'true' : 'false'">
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
                        <p class="mt-1 text-sm text-stone-600">Check whether your current plan still allows another loyalty card for this store.</p>
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
                            Open default QR
                        </x-ui.button>
                        <x-ui.button href="{{ route('merchant.stores.programs.index', $store) }}" variant="ghost" size="sm">
                            Loyalty Cards
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
