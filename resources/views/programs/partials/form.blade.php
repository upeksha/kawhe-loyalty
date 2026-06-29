@php
    $program = $program ?? null;
    $isEdit = $program !== null;
    $formConfig = old('registration_form_config', $program?->registration_form_config ?? \App\Models\Store::defaultRegistrationFormConfig());
@endphp

<div
    class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8"
    x-data="{
        brandColor: @js(old('brand_color', $program?->brand_color ?? $store->brand_color ?? '#0EA5E9')),
        bgColor: @js(old('background_color', $program?->background_color ?? $store->background_color ?? '#1F2937')),
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
>
    <div class="mb-8">
        <p class="text-sm text-stone-500">{{ $store->name }}</p>
        <h1 class="text-2xl font-bold text-stone-900">{{ $title }}</h1>
        <p class="mt-1 text-sm text-stone-600">{{ $subtitle }}</p>
        @if(!$isEdit && isset($usageStats))
            <p class="mt-3 text-xs text-stone-500">
                Plan usage: {{ $usageStats['stores_count'] ?? 0 }}/{{ $usageStats['stores_limit'] ?? 1 }} stores,
                {{ $usageStats['primary_store_programs_count'] ?? $usageStats['programs_count'] }}/{{ $usageStats['programs_per_store_limit'] ?? 1 }} cards on this store.
                {{ $usageStats['is_subscribed'] ? 'Pro: up to '.config('billing.plans.pro.stores').' stores and '.config('billing.plans.pro.programs_per_store').' cards per store.' : 'Free: 1 store, 1 card, 100 customers per card.' }}
            </p>
        @endif
    </div>

    <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_320px]">
        @csrf
        @if($method !== 'POST')
            @method($method)
        @endif

        <div class="space-y-6 rounded-3xl border border-stone-200 bg-white p-6 shadow-sm">
            <div>
                <label for="name" class="block text-sm font-medium text-stone-700 mb-1.5">Card name</label>
                <input type="text" id="name" name="name" value="{{ old('name', $program?->name ?? $program?->reward_title ?? 'Coffee card') }}" class="w-full rounded-xl border border-stone-300 px-4 py-3 text-sm" required>
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="reward_target" class="block text-sm font-medium text-stone-700 mb-1.5">Stamps needed for reward</label>
                    @if($hasIssuedCards)
                        <input type="hidden" name="reward_target" value="{{ old('reward_target', $program?->reward_target) }}">
                        <input type="number" id="reward_target" value="{{ old('reward_target', $program?->reward_target) }}" class="w-full rounded-xl border border-stone-300 bg-stone-100 px-4 py-3 text-sm text-stone-500" readonly>
                        <p class="mt-1 text-xs text-stone-500">This threshold is locked because customers already joined this loyalty card.</p>
                    @else
                        <input type="number" id="reward_target" name="reward_target" value="{{ old('reward_target', $program?->reward_target ?? 9) }}" min="1" class="w-full rounded-xl border border-stone-300 px-4 py-3 text-sm" required>
                    @endif
                    <x-input-error :messages="$errors->get('reward_target')" class="mt-2" />
                </div>

                <div>
                    <label for="reward_title" class="block text-sm font-medium text-stone-700 mb-1.5">Reward title</label>
                    <input type="text" id="reward_title" name="reward_title" value="{{ old('reward_title', $program?->reward_title ?? 'Free coffee') }}" class="w-full rounded-xl border border-stone-300 px-4 py-3 text-sm" required>
                    <x-input-error :messages="$errors->get('reward_title')" class="mt-2" />
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="brand_color" class="block text-sm font-medium text-stone-700 mb-1.5">Brand color</label>
                    <input type="color" id="brand_color" name="brand_color" x-model="brandColor" value="{{ old('brand_color', $program?->brand_color ?? $store->brand_color ?? '#0EA5E9') }}" class="color-swatch-input h-11 w-11 rounded-full border-2 cursor-pointer flex-shrink-0 overflow-hidden p-0 bg-transparent appearance-none" x-bind:class="hasBlockedContrast ? 'border-red-300' : 'border-stone-300'">
                    <x-input-error :messages="$errors->get('brand_color')" class="mt-2" />
                </div>
                <div>
                    <label for="background_color" class="block text-sm font-medium text-stone-700 mb-1.5">Background color</label>
                    <input type="color" id="background_color" name="background_color" x-model="bgColor" value="{{ old('background_color', $program?->background_color ?? $store->background_color ?? '#1F2937') }}" class="color-swatch-input h-11 w-11 rounded-full border-2 cursor-pointer flex-shrink-0 overflow-hidden p-0 bg-transparent appearance-none" x-bind:class="hasBlockedContrast ? 'border-red-300' : 'border-stone-300'">
                    <x-input-error :messages="$errors->get('background_color')" class="mt-2" />
                </div>
            </div>

            <div x-show="hasBlockedContrast" x-cloak class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                <p class="font-semibold">Colors need more contrast</p>
                <p class="mt-1 leading-relaxed">
                    Your brand and background colors are too close together, so the join page and saved card can become unreadable. Use a darker background, a brighter accent, or increase the contrast between them before saving.
                </p>
            </div>

            <div x-show="!hasBlockedContrast && (hasLowContrastPreview || hasVeryLightBackground)" x-cloak class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <p class="font-semibold">Preview warning</p>
                <p class="mt-1 leading-relaxed">
                    This combination will save, but it may still look washed out on the join page or wallet handoff. The safest fix is a darker background and a more distinct accent color.
                </p>
            </div>

            <div class="grid grid-cols-1 items-stretch gap-4 sm:grid-cols-3">
                @php
                    $logoPreviewUrl = $isEdit ? $program?->logo_url : $store->logo_url;
                    $passLogoPreviewUrl = $isEdit ? $program?->pass_logo_url : $store->pass_logo_url;
                    $passHeroPreviewUrl = $isEdit ? $program?->pass_hero_image_url : $store->pass_hero_image_url;
                    $assetPreviewLabel = $isEdit ? 'Current' : 'From store';
                    $imageFields = [
                        ['id' => 'logo', 'label' => 'Logo', 'previewUrl' => $logoPreviewUrl, 'imgClass' => 'h-20 w-20 object-contain'],
                        ['id' => 'pass_logo', 'label' => 'Wallet logo', 'previewUrl' => $passLogoPreviewUrl, 'imgClass' => 'h-12 w-20 object-contain'],
                        ['id' => 'pass_hero_image', 'label' => 'Wallet hero', 'previewUrl' => $passHeroPreviewUrl, 'imgClass' => 'h-20 w-full max-w-[8rem] object-cover'],
                    ];
                @endphp

                @foreach($imageFields as $field)
                    <x-ui.image-upload-field
                        :id="$field['id']"
                        :label="$field['label']"
                        :preview-url="$field['previewUrl']"
                        :img-class="$field['imgClass']"
                        :existing-label="$assetPreviewLabel"
                    >
                        <x-input-error :messages="$errors->get($field['id'])" class="mt-2" />
                    </x-ui.image-upload-field>
                @endforeach
            </div>
            @if(!$isEdit && ($store->logo_path || $store->pass_logo_path || $store->pass_hero_image_path))
                <p class="text-xs text-stone-500">Uses your store images by default. Upload only if this card needs different artwork.</p>
            @endif

            <div class="rounded-2xl border border-stone-200 bg-stone-50 p-5">
                <div class="flex items-start gap-3">
                    <input type="checkbox" id="require_verification_for_redemption" name="require_verification_for_redemption" value="1" class="mt-1 rounded border-stone-300" {{ old('require_verification_for_redemption', $program?->require_verification_for_redemption ?? true) ? 'checked' : '' }}>
                    <div>
                        <label for="require_verification_for_redemption" class="block text-sm font-medium text-stone-800">Require email verification before redemption</label>
                        <p class="mt-1 text-xs text-stone-500">Leave this on for stronger fraud protection.</p>
                    </div>
                </div>
            </div>

            @php
                $registrationFormConfig = \App\Support\RegistrationFormConfig::normalize($formConfig);
            @endphp
            <div
                class="rounded-2xl border border-stone-200 bg-stone-50 p-5"
                x-data="registrationFormConfigState(@js($registrationFormConfig))"
            >
                <p class="text-sm font-medium text-stone-800 mb-4">Join form fields</p>
                <x-registration-form-config-editor
                    :config="$registrationFormConfig"
                    show-presets
                    :show-preview="false"
                />
            </div>

            <div class="flex flex-wrap gap-3">
                <x-ui.button type="submit" variant="primary" x-bind:disabled="hasBlockedContrast" x-bind:aria-disabled="hasBlockedContrast ? 'true' : 'false'">{{ $isEdit ? 'Save loyalty card' : 'Create loyalty card' }}</x-ui.button>
                <x-ui.button href="{{ route('merchant.stores.programs.index', $store) }}" variant="secondary">Back to cards</x-ui.button>
                @if($isEdit)
                    <x-ui.button href="{{ route('merchant.stores.programs.qr', [$store, $program]) }}" variant="ghost">View QR</x-ui.button>
                @endif
            </div>
        </div>

        <div class="rounded-3xl border border-stone-200 bg-stone-50 p-6 shadow-sm h-fit">
            <h2 class="text-lg font-semibold text-stone-900">How this works</h2>
            <ul class="mt-3 space-y-2 text-sm text-stone-600">
                <li>Each loyalty card gets its own join link and QR code.</li>
                <li>Customers who join this card keep separate progress from your other cards.</li>
                <li>If customers already joined, the reward target locks to protect their existing progress.</li>
            </ul>
            @if(!$isEdit && isset($usageStats))
                <div class="mt-4 rounded-2xl border border-stone-200 bg-white p-4 text-sm text-stone-600">
                    <p class="font-semibold text-stone-800">Plan limit</p>
                    <p class="mt-1">
                        {{ $usageStats['is_subscribed'] ? 'Pro plan:' : 'Free plan:' }}
                        {{ $usageStats['stores_limit'] ?? 1 }} store{{ ($usageStats['stores_limit'] ?? 1) === 1 ? '' : 's' }},
                        {{ $usageStats['programs_per_store_limit'] ?? 1 }} card{{ ($usageStats['programs_per_store_limit'] ?? 1) === 1 ? '' : 's' }} per store,
                        @if($usageStats['customers_per_program_limit'] ?? null)
                            {{ $usageStats['customers_per_program_limit'] }} customers per card.
                        @else
                            unlimited customers per card.
                        @endif
                    </p>
                </div>
            @endif
        </div>
    </form>
</div>
