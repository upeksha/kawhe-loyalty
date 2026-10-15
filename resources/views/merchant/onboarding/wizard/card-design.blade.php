@php
    $brandColor = old('brand_color', $store->brand_color ?? '#0EA5E9');
    $bgColor = old('background_color', $store->background_color ?? '#1F2937');
    $inputClass = 'block w-full rounded-xl border border-stone-300 bg-white px-4 py-2.5 text-base text-stone-900 placeholder-stone-400 transition-colors focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500';
@endphp
<x-onboarding-layout>
    <x-slot name="header">Card design</x-slot>

    <x-onboarding-step-layout
        :step="2"
        :totalSteps="4"
        title="Design your loyalty card"
        subtitle="Choose your colours and add three images. Preview your card before moving on."
        :backUrl="route('merchant.onboarding.wizard.store-basics')"
    >
        <form method="POST" action="{{ route('merchant.onboarding.wizard.card-design.store') }}" enctype="multipart/form-data" id="card-design-form"
        x-data="{
            storeName: {{ json_encode($store->name ?? '') }},
            rewardTarget: {{ (int) ($store->reward_target ?? 9) }},
            rewardTitle: {{ json_encode($store->reward_title ?? 'Free coffee') }},
            brandColor: '{{ $brandColor }}',
            bgColor: '{{ $bgColor }}',
            logoPreview: '{{ $store->logo_url ?? '' }}',
            passLogoPreview: '{{ $store->pass_logo_url ?? '' }}',
            passHeroPreview: '{{ $store->pass_hero_image_url ?? '' }}',
            hexToRgb(hex) {
                const cleaned = (hex || '').replace('#', '');
                if (cleaned.length !== 6) return null;
                const value = parseInt(cleaned, 16);
                if (Number.isNaN(value)) return null;
                return {
                    r: (value >> 16) & 255,
                    g: (value >> 8) & 255,
                    b: value & 255,
                };
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
            get hasRequiredAssets() {
                return Boolean(this.logoPreview && this.passLogoPreview && this.passHeroPreview);
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
            $refs.brandColor && $refs.brandColor.addEventListener('input', e => { brandColor = e.target.value; $refs.brandColorText && ($refs.brandColorText.value = e.target.value); });
            $refs.brandColorText && $refs.brandColorText.addEventListener('input', e => { if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) { brandColor = e.target.value; $refs.brandColor && ($refs.brandColor.value = e.target.value); } });
            $refs.bgColor && $refs.bgColor.addEventListener('input', e => { bgColor = e.target.value; $refs.bgColorText && ($refs.bgColorText.value = e.target.value); });
            $refs.bgColorText && $refs.bgColorText.addEventListener('input', e => { if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) { bgColor = e.target.value; $refs.bgColor && ($refs.bgColor.value = e.target.value); } });
            $refs.logoInput && $refs.logoInput.addEventListener('change', e => { const f = e.target.files[0]; if (f) logoPreview = URL.createObjectURL(f); });
            $refs.passLogoInput && $refs.passLogoInput.addEventListener('change', e => { const f = e.target.files[0]; if (f) passLogoPreview = URL.createObjectURL(f); });
            $refs.passHeroInput && $refs.passHeroInput.addEventListener('change', e => { const f = e.target.files[0]; if (f) passHeroPreview = URL.createObjectURL(f); });
        "
        >
            @csrf
            <x-form-error-summary form-id="card-design-form" />
            <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
                <div class="lg:col-span-3 space-y-8">
                    <x-onboarding-form-section title="1. Choose your colours">
                        <div class="space-y-5">
                            <div>
                                <label for="brand_color" class="block text-sm font-medium text-stone-700 mb-1.5">Accent colour</label>
                                <div class="flex gap-3 mt-1.5">
                                    <input type="color" id="brand_color" name="brand_color" x-ref="brandColor" value="{{ $brandColor }}" class="color-swatch-input h-11 w-11 rounded-full border-2 cursor-pointer flex-shrink-0 overflow-hidden p-0 bg-transparent appearance-none" x-bind:class="hasBlockedContrast ? 'border-red-300' : 'border-stone-300'" />
                                    <input type="text" id="brand_color_text" x-ref="brandColorText" value="{{ $brandColor }}" placeholder="#0EA5E9" aria-label="Accent colour hex code" required pattern="#[0-9A-Fa-f]{6}" maxlength="7" title="Enter a colour code such as #0EA5E9" aria-describedby="brand_color_note" autocapitalize="off" spellcheck="false" class="{{ $inputClass }}" x-bind:class="hasBlockedContrast ? '!border-red-300 !ring-red-100' : ''" />
                                </div>
                                <x-onboarding-helper-note id="brand_color_note">Used for buttons and accents on the customer card.</x-onboarding-helper-note>
                                <x-input-error :messages="$errors->get('brand_color')" class="mt-2" />
                            </div>
                            <div>
                                <label for="background_color" class="block text-sm font-medium text-stone-700 mb-1.5">Card background</label>
                                <div class="flex gap-3 mt-1.5">
                                    <input type="color" id="background_color" name="background_color" x-ref="bgColor" value="{{ $bgColor }}" class="color-swatch-input h-11 w-11 rounded-full border-2 cursor-pointer flex-shrink-0 overflow-hidden p-0 bg-transparent appearance-none" x-bind:class="hasBlockedContrast ? 'border-red-300' : 'border-stone-300'" />
                                    <input type="text" id="background_color_text" x-ref="bgColorText" value="{{ $bgColor }}" placeholder="#1F2937" aria-label="Background colour hex code" required pattern="#[0-9A-Fa-f]{6}" maxlength="7" title="Enter a colour code such as #1F2937" aria-describedby="background_color_note" autocapitalize="off" spellcheck="false" class="{{ $inputClass }}" x-bind:class="hasBlockedContrast ? '!border-red-300 !ring-red-100' : ''" />
                                </div>
                                <x-onboarding-helper-note id="background_color_note">Background of the join page and card.</x-onboarding-helper-note>
                                <x-input-error :messages="$errors->get('background_color')" class="mt-2" />
                            </div>
                            <div x-show="hasBlockedContrast" x-cloak>
                                <x-ui.alert variant="danger">
                                    <p class="font-semibold">Choose more distinct colours</p>
                                    <p class="mt-1 leading-relaxed">
                                        Your brand and background colors are too close together, so the join page and saved card can become unreadable. Use a darker background, a brighter accent, or increase the contrast between them before continuing.
                                    </p>
                                </x-ui.alert>
                            </div>
                            <div x-show="!hasBlockedContrast && (hasLowContrastPreview || hasVeryLightBackground)" x-cloak>
                                <x-ui.alert variant="warning">
                                    <p class="font-semibold">Check your colours</p>
                                    <p class="mt-1 leading-relaxed">
                                        This combination will save, but it may still look washed out on the join page or wallet handoff. The safest fix is a darker background and a more distinct accent color.
                                    </p>
                                </x-ui.alert>
                            </div>
                        </div>
                    </x-onboarding-form-section>

                    <x-onboarding-form-section title="2. Add your store logo">
                        <div>
                            <label for="logo" class="block text-sm font-medium text-stone-700 mb-1.5">Store logo <span class="text-red-500">*</span></label>
                            <div class="mt-2 flex flex-col sm:flex-row sm:items-start gap-4">
                                <div x-show="logoPreview" class="flex-shrink-0">
                                    <p class="text-xs font-medium text-stone-500 mb-1.5">Preview</p>
                                    <img :src="logoPreview" alt="Logo preview" class="h-20 w-20 object-contain rounded-xl border border-stone-200 bg-white p-1.5 shadow-sm">
                                </div>
                                <div class="flex-1 min-w-0">
                                    <label class="block w-full rounded-xl border-2 border-dashed border-stone-300 hover:border-stone-400 hover:bg-stone-50/50 bg-stone-50/30 px-4 py-6 text-center cursor-pointer transition-all focus-within:ring-2 focus-within:ring-brand-500/20 focus-within:border-brand-500">
                                        <span class="text-sm font-medium text-stone-600">Choose file</span>
                                        <input type="file" id="logo" name="logo" @required(blank($store->logo_path)) x-ref="logoInput" accept="image/png,image/jpeg,image/jpg,image/webp" class="sr-only" />
                                    </label>
                                    <x-onboarding-helper-note class="mt-1.5">PNG, JPG or WebP, max 2MB. Shown on customer card.</x-onboarding-helper-note>
                                    <p class="mt-2 text-xs leading-relaxed text-stone-500">Best results usually come from a simple square or circular logo with generous empty space around it.</p>
                                </div>
                            </div>
                            <x-input-error :messages="$errors->get('logo')" class="mt-2" />
                        </div>
                    </x-onboarding-form-section>

                    <x-onboarding-form-section title="3. Add your Wallet images">
                        <p class="text-sm text-stone-600 mb-4">These two images appear when customers save their card to Apple Wallet or Google Wallet. PNG, JPG or WebP, up to 2MB each.</p>
                        <div class="space-y-5">
                            <div>
                                <label for="pass_logo" class="block text-sm font-medium text-stone-700 mb-1.5">Wallet logo <span class="text-red-500">*</span></label>
                                <div class="mt-2 flex flex-col sm:flex-row sm:items-start gap-4">
                                    <div x-show="passLogoPreview" class="flex-shrink-0">
                                        <p class="text-xs font-medium text-stone-500 mb-1.5">Preview</p>
                                        <img :src="passLogoPreview" alt="Pass logo preview" class="h-12 w-20 object-contain rounded-xl border border-stone-200 bg-white p-1 shadow-sm">
                                    </div>
                                    <label class="rounded-xl border-2 border-dashed border-stone-300 hover:border-stone-400 bg-stone-50/30 hover:bg-stone-50/50 px-4 py-3 text-center cursor-pointer transition-all flex-1">
                                        <span class="text-sm font-medium text-stone-600">Choose file</span>
                                        <input type="file" id="pass_logo" name="pass_logo" @required(blank($store->pass_logo_path)) x-ref="passLogoInput" accept="image/png,image/jpeg,image/jpg,image/webp" class="sr-only" />
                                    </label>
                                </div>
                                <x-onboarding-helper-note>Your logo across the top of the saved card. Recommended: 160×50px.</x-onboarding-helper-note>
                                <x-input-error :messages="$errors->get('pass_logo')" class="mt-2" />
                            </div>
                            <div>
                                <label for="pass_hero_image" class="block text-sm font-medium text-stone-700 mb-1.5">Wallet banner <span class="text-red-500">*</span></label>
                                <div class="mt-2 flex flex-col sm:flex-row sm:items-start gap-4">
                                    <div x-show="passHeroPreview" class="flex-shrink-0">
                                        <p class="text-xs font-medium text-stone-500 mb-1.5">Preview</p>
                                        <img :src="passHeroPreview" alt="Hero preview" class="h-16 w-28 object-cover rounded-xl border border-stone-200 shadow-sm">
                                    </div>
                                    <label class="rounded-xl border-2 border-dashed border-stone-300 hover:border-stone-400 bg-stone-50/30 hover:bg-stone-50/50 px-4 py-3 text-center cursor-pointer transition-all flex-1">
                                        <span class="text-sm font-medium text-stone-600">Choose file</span>
                                        <input type="file" id="pass_hero_image" name="pass_hero_image" @required(blank($store->pass_hero_image_path)) x-ref="passHeroInput" accept="image/png,image/jpeg,image/jpg,image/webp" class="sr-only" />
                                    </label>
                                </div>
                                <x-onboarding-helper-note>A wide image for your saved card. Recommended: 640×180px (Apple) or 640×200px (Google).</x-onboarding-helper-note>
                                <x-input-error :messages="$errors->get('pass_hero_image')" class="mt-2" />
                            </div>
                        </div>
                    </x-onboarding-form-section>
                </div>

                <aside class="min-w-0 lg:col-span-2">
                    <div class="lg:sticky lg:top-8">
                        <h2 class="mb-3 text-lg font-semibold text-stone-900">Card preview</h2>
                        <x-wallet-pass-preview :preview-data="$walletPreview ?? []" />
                        <p class="mt-3 text-sm text-stone-500">Sample card. Check both Wallet previews before continuing.</p>
                    </div>
                </aside>
            </div>
            <div class="mt-6 border-t border-stone-200 pt-5">
                <p x-show="!hasRequiredAssets" class="mb-3 text-sm text-stone-600">Add your store logo, Wallet logo and Wallet banner to continue. Saved images are kept unless you replace them.</p>
                <p x-show="hasBlockedContrast" x-cloak class="mb-3 text-sm text-red-700">Choose more distinct accent and background colours to continue.</p>
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <a href="{{ route('merchant.onboarding.wizard.store-basics') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl px-4 text-sm font-semibold text-stone-600">Back to reward setup</a>
                    <x-ui.button type="submit" variant="primary" size="lg" class="min-h-11 w-full sm:w-auto" x-bind:disabled="hasBlockedContrast || !hasRequiredAssets">Save and continue → Customer signup</x-ui.button>
                </div>
            </div>
        </form>
    </x-onboarding-step-layout>
</x-onboarding-layout>
