@php
    $brandColor = old('brand_color', $store->brand_color ?? '#0EA5E9');
    $bgColor = old('background_color', $store->background_color ?? '#1F2937');
    $inputClass = 'block w-full rounded-xl border border-stone-300 bg-white px-4 py-2.5 text-sm text-stone-900 placeholder-stone-400 transition-colors focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500';
@endphp
<x-onboarding-layout>
    <x-slot name="header">Card design</x-slot>

    <x-onboarding-step-layout
        :step="2"
        :totalSteps="4"
        title="Make it yours"
        subtitle="Upload your store logo and wallet images, and choose colors that stay readable on the join page, customer card, and Apple/Google Wallet passes."
        :backUrl="route('merchant.onboarding.wizard.store-basics')"
    >
        <form method="POST" action="{{ route('merchant.onboarding.wizard.card-design.store') }}" enctype="multipart/form-data" id="card-design-form"
        x-data="{
            storeName: '{{ addslashes($store->name ?? '') }}',
            rewardTarget: {{ (int) ($store->reward_target ?? 9) }},
            rewardTitle: '{{ addslashes($store->reward_title ?? 'Free coffee') }}',
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
            get launchScore() {
                let score = 0;
                if (this.brandColor) score++;
                if (this.bgColor) score++;
                if (this.logoPreview) score++;
                if (this.passLogoPreview) score++;
                if (this.passHeroPreview) score++;
                if (!this.hasLowContrastPreview && !this.hasVeryLightBackground) score++;
                return score;
            },
            get launchLabel() {
                if (this.launchScore >= 6) return 'Good to launch';
                if (this.launchScore >= 4) return 'Almost ready';
                return 'Needs review';
            },
            get launchTone() {
                if (this.launchScore >= 6) return 'bg-emerald-100 text-emerald-700';
                if (this.launchScore >= 4) return 'bg-amber-100 text-amber-700';
                return 'bg-accent-100 text-accent-700';
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
            <div class="grid grid-cols-1 sm:grid-cols-5 gap-6">
                <div class="sm:col-span-3 space-y-8">
                    <x-onboarding-form-section title="Brand colors">
                        <div class="space-y-5">
                            <div>
                                <label for="brand_color" class="block text-sm font-medium text-stone-700 mb-1.5">Brand color</label>
                                <div class="flex gap-3 mt-1.5">
                                    <input type="color" id="brand_color" name="brand_color" x-ref="brandColor" value="{{ $brandColor }}" class="color-swatch-input h-11 w-11 rounded-full border-2 cursor-pointer flex-shrink-0 overflow-hidden p-0 bg-transparent appearance-none" x-bind:class="hasBlockedContrast ? 'border-red-300' : 'border-stone-300'" />
                                    <input type="text" id="brand_color_text" x-ref="brandColorText" value="{{ $brandColor }}" placeholder="#0EA5E9" aria-label="Brand color hex value" aria-describedby="brand_color_note" autocapitalize="off" spellcheck="false" class="{{ $inputClass }}" x-bind:class="hasBlockedContrast ? '!border-red-300 !ring-red-100' : ''" />
                                </div>
                                <x-onboarding-helper-note id="brand_color_note">Used for buttons and accents on the customer card.</x-onboarding-helper-note>
                                <x-input-error :messages="$errors->get('brand_color')" class="mt-2" />
                            </div>
                            <div>
                                <label for="background_color" class="block text-sm font-medium text-stone-700 mb-1.5">Background color</label>
                                <div class="flex gap-3 mt-1.5">
                                    <input type="color" id="background_color" name="background_color" x-ref="bgColor" value="{{ $bgColor }}" class="color-swatch-input h-11 w-11 rounded-full border-2 cursor-pointer flex-shrink-0 overflow-hidden p-0 bg-transparent appearance-none" x-bind:class="hasBlockedContrast ? 'border-red-300' : 'border-stone-300'" />
                                    <input type="text" id="background_color_text" x-ref="bgColorText" value="{{ $bgColor }}" placeholder="#1F2937" aria-label="Background color hex value" aria-describedby="background_color_note" autocapitalize="off" spellcheck="false" class="{{ $inputClass }}" x-bind:class="hasBlockedContrast ? '!border-red-300 !ring-red-100' : ''" />
                                </div>
                                <x-onboarding-helper-note id="background_color_note">Background of the join page and card.</x-onboarding-helper-note>
                                <x-input-error :messages="$errors->get('background_color')" class="mt-2" />
                            </div>
                            <div x-show="hasBlockedContrast" x-cloak>
                                <x-ui.alert variant="danger">
                                    <p class="font-semibold">Colors need more contrast</p>
                                    <p class="mt-1 leading-relaxed">
                                        Your brand and background colors are too close together, so the join page and saved card can become unreadable. Use a darker background, a brighter accent, or increase the contrast between them before continuing.
                                    </p>
                                </x-ui.alert>
                            </div>
                            <div x-show="!hasBlockedContrast && (hasLowContrastPreview || hasVeryLightBackground)" x-cloak>
                                <x-ui.alert variant="warning">
                                    <p class="font-semibold">Preview warning</p>
                                    <p class="mt-1 leading-relaxed">
                                        This combination will save, but it may still look washed out on the join page or wallet handoff. The safest fix is a darker background and a more distinct accent color.
                                    </p>
                                </x-ui.alert>
                            </div>
                            <div class="rounded-xl border border-stone-200 bg-stone-50/80 p-4">
                                <p class="text-sm font-semibold text-stone-800">Recommended approach</p>
                                <ul class="mt-2 space-y-2 text-sm leading-relaxed text-stone-600">
                                    <li>Use a darker background with a brighter accent for the safest wallet and join-page readability.</li>
                                    <li>Upload a square store logo plus wallet pass logo and hero image before continuing.</li>
                                    <li>Choose colors customers can recognise quickly from the counter or poster, not just on a laptop screen.</li>
                                </ul>
                            </div>
                        </div>
                    </x-onboarding-form-section>

                    <x-onboarding-form-section title="Store branding">
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
                                        <input type="file" id="logo" name="logo" x-ref="logoInput" accept="image/png,image/jpeg,image/jpg,image/webp" class="sr-only" />
                                    </label>
                                    <x-onboarding-helper-note class="mt-1.5">PNG, JPG or WebP, max 2MB. Shown on customer card.</x-onboarding-helper-note>
                                    <p class="mt-2 text-xs leading-relaxed text-stone-500">Best results usually come from a simple square or circular logo with generous empty space around it.</p>
                                </div>
                            </div>
                            <x-input-error :messages="$errors->get('logo')" class="mt-2" />
                        </div>
                    </x-onboarding-form-section>

                    <x-onboarding-form-section title="Wallet assets">
                        <p class="text-sm text-stone-600 mb-4">Required for Apple Wallet and Google Wallet passes. Upload both images so customers see a polished card when they save it.</p>
                        <div class="space-y-5">
                            <div>
                                <label for="pass_logo" class="block text-sm font-medium text-stone-700 mb-1.5">Pass logo <span class="text-red-500">*</span></label>
                                <div class="mt-2 flex flex-col sm:flex-row sm:items-start gap-4">
                                    <div x-show="passLogoPreview" class="flex-shrink-0">
                                        <p class="text-xs font-medium text-stone-500 mb-1.5">Preview</p>
                                        <img :src="passLogoPreview" alt="Pass logo preview" class="h-12 w-20 object-contain rounded-xl border border-stone-200 bg-white p-1 shadow-sm">
                                    </div>
                                    <label class="rounded-xl border-2 border-dashed border-stone-300 hover:border-stone-400 bg-stone-50/30 hover:bg-stone-50/50 px-4 py-3 text-center cursor-pointer transition-all flex-1">
                                        <span class="text-sm font-medium text-stone-600">Choose file</span>
                                        <input type="file" id="pass_logo" name="pass_logo" x-ref="passLogoInput" accept="image/png,image/jpeg,image/jpg,image/webp" class="sr-only" />
                                    </label>
                                </div>
                                <x-onboarding-helper-note>Recommended: 160×50px.</x-onboarding-helper-note>
                                <x-input-error :messages="$errors->get('pass_logo')" class="mt-2" />
                            </div>
                            <div>
                                <label for="pass_hero_image" class="block text-sm font-medium text-stone-700 mb-1.5">Pass hero image <span class="text-red-500">*</span></label>
                                <div class="mt-2 flex flex-col sm:flex-row sm:items-start gap-4">
                                    <div x-show="passHeroPreview" class="flex-shrink-0">
                                        <p class="text-xs font-medium text-stone-500 mb-1.5">Preview</p>
                                        <img :src="passHeroPreview" alt="Hero preview" class="h-16 w-28 object-cover rounded-xl border border-stone-200 shadow-sm">
                                    </div>
                                    <label class="rounded-xl border-2 border-dashed border-stone-300 hover:border-stone-400 bg-stone-50/30 hover:bg-stone-50/50 px-4 py-3 text-center cursor-pointer transition-all flex-1">
                                        <span class="text-sm font-medium text-stone-600">Choose file</span>
                                        <input type="file" id="pass_hero_image" name="pass_hero_image" x-ref="passHeroInput" accept="image/png,image/jpeg,image/jpg,image/webp" class="sr-only" />
                                    </label>
                                </div>
                                <x-onboarding-helper-note>Banner. Recommended: 640×180px (Apple) or 640×200px (Google).</x-onboarding-helper-note>
                                <x-input-error :messages="$errors->get('pass_hero_image')" class="mt-2" />
                            </div>
                        </div>
                    </x-onboarding-form-section>
                </div>

                <div class="sm:col-span-2">
                    <details class="sm:hidden rounded-xl border border-stone-200 bg-stone-50/70 p-4">
                        <summary class="cursor-pointer list-none flex items-center justify-between text-sm font-semibold text-stone-800">
                            Wallet pass preview
                            <span class="text-xs font-medium text-stone-500">Tap to expand</span>
                        </summary>
                        <div class="mt-4">
                            <x-wallet-pass-preview />
                        </div>
                    </details>
                    <div class="hidden sm:block sticky top-8">
                        <x-wallet-pass-preview />
                        <div class="mt-4 rounded-2xl border border-stone-200 bg-stone-50/80 p-5 shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Launch quality</p>
                                    <p class="mt-1 text-sm text-stone-600">A quick read on whether this card will feel polished before customers save it.</p>
                                </div>
                                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold" x-bind:class="launchTone" x-text="launchLabel"></span>
                            </div>
                            <p class="mt-3 text-xs text-stone-500" x-text="launchScore + ' of 6 launch signals are in a strong place.'"></p>
                            <p class="mt-4 text-xs font-semibold uppercase tracking-wider text-stone-500">Setup status</p>
                            <div class="mt-4 space-y-3">
                                <x-ui.readiness-row label="Brand accent chosen">
                                    <x-slot name="status">
                                        <span class="font-medium" x-bind:class="brandColor ? 'text-emerald-700' : 'text-stone-500'" x-text="brandColor ? 'Ready' : 'Needed'"></span>
                                    </x-slot>
                                </x-ui.readiness-row>
                                <x-ui.readiness-row label="Background chosen">
                                    <x-slot name="status">
                                        <span class="font-medium" x-bind:class="bgColor ? 'text-emerald-700' : 'text-stone-500'" x-text="bgColor ? 'Ready' : 'Needed'"></span>
                                    </x-slot>
                                </x-ui.readiness-row>
                                <x-ui.readiness-row label="Store logo">
                                    <x-slot name="status">
                                        <span class="font-medium" x-bind:class="logoPreview ? 'text-emerald-700' : 'text-stone-500'" x-text="logoPreview ? 'Ready' : 'Needed'"></span>
                                    </x-slot>
                                </x-ui.readiness-row>
                                <x-ui.readiness-row label="Pass logo">
                                    <x-slot name="status">
                                        <span class="font-medium" x-bind:class="passLogoPreview ? 'text-emerald-700' : 'text-stone-500'" x-text="passLogoPreview ? 'Ready' : 'Needed'"></span>
                                    </x-slot>
                                </x-ui.readiness-row>
                                <x-ui.readiness-row label="Pass hero image">
                                    <x-slot name="status">
                                        <span class="font-medium" x-bind:class="passHeroPreview ? 'text-emerald-700' : 'text-stone-500'" x-text="passHeroPreview ? 'Ready' : 'Needed'"></span>
                                    </x-slot>
                                </x-ui.readiness-row>
                                <x-ui.readiness-row label="Visual contrast">
                                    <x-slot name="status">
                                        <span class="font-medium" x-bind:class="hasBlockedContrast ? 'text-red-700' : ((hasLowContrastPreview || hasVeryLightBackground) ? 'text-amber-700' : 'text-emerald-700')" x-text="hasBlockedContrast ? 'Blocked' : ((hasLowContrastPreview || hasVeryLightBackground) ? 'Review' : 'Ready')"></span>
                                    </x-slot>
                                </x-ui.readiness-row>
                            </div>
                            <p class="mt-4 text-xs leading-relaxed text-stone-500">Unreadable color combinations are blocked. Borderline combinations still save, but we warn before customers see the card.</p>
                        </div>
                        <div class="mt-4 rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                            <p class="text-sm font-semibold text-stone-800">Asset guidance</p>
                            <ul class="mt-3 space-y-2 text-sm leading-relaxed text-stone-600">
                                <li>Use a clean logo with plenty of empty space so it still reads at small wallet sizes.</li>
                                <li>Pick a hero image with one strong subject or texture, not a collage of text-heavy elements.</li>
                                <li>Upload all three images before continuing — they power the join page, wallet pass, and poster.</li>
                            </ul>
                        </div>
                        @if($store->pass_logo_path || $store->pass_hero_image_path)
                            <p class="mt-3 text-xs text-stone-500 flex items-center gap-1.5">
                                <span class="inline-flex items-center gap-1 rounded-md bg-stone-200 px-1.5 py-0.5">Apple Wallet</span>
                                <span class="inline-flex items-center gap-1 rounded-md bg-stone-200 px-1.5 py-0.5">Google Wallet</span>
                                ready
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </form>

        <x-slot name="actions">
            <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3">
                <form method="GET" action="{{ route('merchant.onboarding.wizard.store-basics') }}" class="w-full sm:w-auto">
                    <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 text-sm font-medium text-stone-600 hover:text-stone-900 transition-colors focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 rounded-lg py-2 px-3">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        Back
                    </button>
                </form>
                <x-ui.button type="submit" form="card-design-form" variant="primary" size="lg" class="w-full sm:w-auto rounded-xl min-w-[140px]" x-bind:disabled="hasBlockedContrast || !hasRequiredAssets" x-bind:aria-disabled="(hasBlockedContrast || !hasRequiredAssets) ? 'true' : 'false'">Continue</x-ui.button>
            </div>
        </x-slot>
    </x-onboarding-step-layout>
</x-onboarding-layout>
