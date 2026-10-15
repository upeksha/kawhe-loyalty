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
        subtitle="Choose a style, add your branding and preview your card before moving on."
        :backUrl="route('merchant.onboarding.wizard.store-basics')"
    >
        <form method="POST" action="{{ route('merchant.onboarding.wizard.card-design.store') }}" enctype="multipart/form-data" id="card-design-form"
        x-data="{
            cardDesign: @js(old('card_design', \App\Support\CardDesign::style($store))),
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
                return Boolean(this.logoPreview && this.passLogoPreview && (this.cardDesign !== 'classic' || this.passHeroPreview));
            },
            get hasLowContrastPreview() {
                return this.cardDesign === 'classic' && this.contrastRatio(this.brandColor, this.bgColor) < 3;
            },
            get hasBlockedContrast() {
                return this.cardDesign === 'classic' && this.contrastRatio(this.brandColor, this.bgColor) < 2;
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
            <x-card-design-choice :model="$store" />
            <x-form-error-summary form-id="card-design-form" />
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-brand-50 px-4 py-3 text-sm">
                <p class="text-brand-900">Designing for <strong>{{ $store->name }}</strong></p>
                <a href="#onboarding-card-preview" class="inline-flex min-h-11 items-center font-semibold text-brand-700 underline lg:hidden">See card preview ↓</a>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-5 gap-6 lg:gap-8">
                <div class="lg:col-span-3 space-y-8">
                    <section x-show="cardDesign !== 'photo'" class="rounded-2xl border border-stone-200 bg-white p-5 sm:p-6"><h2 class="mb-2 text-lg font-semibold text-stone-900">Choose your background colour</h2><p class="mb-5 text-sm text-stone-500">Text and stamp colours adjust automatically for contrast.</p>
                        <div class="space-y-5">
                            <div x-show="cardDesign === 'classic'">
                                <label for="brand_color" class="block text-sm font-medium text-stone-700 mb-1.5">Accent colour</label>
                                <div class="flex gap-3 mt-1.5">
                                    <input type="color" id="brand_color" name="brand_color" x-ref="brandColor" value="{{ $brandColor }}" class="color-swatch-input h-11 w-11 rounded-full border-2 cursor-pointer flex-shrink-0 overflow-hidden p-0 bg-transparent appearance-none" x-bind:class="hasBlockedContrast ? 'border-red-300' : 'border-stone-300'" />
                                    <input type="text" id="brand_color_text" :disabled="cardDesign !== 'classic'" x-ref="brandColorText" value="{{ $brandColor }}" placeholder="#0EA5E9" aria-label="Accent colour hex code" required pattern="#[0-9A-Fa-f]{6}" maxlength="7" title="Enter a colour code such as #0EA5E9" aria-describedby="brand_color_note" autocapitalize="off" spellcheck="false" class="{{ $inputClass }}" x-bind:class="hasBlockedContrast ? '!border-red-300 !ring-red-100' : ''" />
                                </div>
                                <x-onboarding-helper-note id="brand_color_note">Used for buttons and accents on the customer card.</x-onboarding-helper-note>
                                <x-input-error :messages="$errors->get('brand_color')" class="mt-2" />
                            </div>
                            <div>
                                <label for="background_color" class="block text-sm font-medium text-stone-700 mb-1.5">Card background</label>
                                <div class="flex gap-3 mt-1.5">
                                    <input type="color" id="background_color" name="background_color" x-ref="bgColor" value="{{ $bgColor }}" class="color-swatch-input h-11 w-11 rounded-full border-2 cursor-pointer flex-shrink-0 overflow-hidden p-0 bg-transparent appearance-none" x-bind:class="hasBlockedContrast ? 'border-red-300' : 'border-stone-300'" />
                                    <input type="text" id="background_color_text" :disabled="cardDesign === 'photo'" x-ref="bgColorText" value="{{ $bgColor }}" placeholder="#1F2937" aria-label="Background colour hex code" required pattern="#[0-9A-Fa-f]{6}" maxlength="7" title="Enter a colour code such as #1F2937" aria-describedby="background_color_note" autocapitalize="off" spellcheck="false" class="{{ $inputClass }}" x-bind:class="hasBlockedContrast ? '!border-red-300 !ring-red-100' : ''" />
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
                    </section>

                    <section class="rounded-2xl border border-stone-200 bg-white p-5 sm:p-6" aria-labelledby="card-images-heading">
                        <h2 id="card-images-heading" class="text-lg font-semibold text-stone-900">Add your images</h2>
                        <p class="mt-2 text-sm text-stone-500">Add your store and Wallet logos. Your selected card style supplies the background artwork. PNG, JPG or WebP, up to 2MB each.</p>
                        @php
                            $uploads = [
                                ['id' => 'logo', 'label' => 'Store logo', 'ref' => 'logoInput', 'preview' => 'logoPreview', 'path' => 'logo_path', 'hint' => 'Shown on your customer card and join page. A square logo works best.', 'shape' => 'h-16 w-16 object-contain'],
                                ['id' => 'pass_logo', 'label' => 'Wallet logo', 'ref' => 'passLogoInput', 'preview' => 'passLogoPreview', 'path' => 'pass_logo_path', 'hint' => 'Across the top of the saved Wallet card. Recommended: 160×50px.', 'shape' => 'h-12 w-24 object-contain'],
                                ['id' => 'pass_hero_image', 'label' => 'Wallet banner', 'ref' => 'passHeroInput', 'preview' => 'passHeroPreview', 'path' => 'pass_hero_image_path', 'hint' => 'A wide image for the saved card. Recommended: 640×180px (Apple) or 640×200px (Google).', 'shape' => 'h-16 w-28 object-cover'],
                            ];
                        @endphp
                        <div class="mt-5 divide-y divide-stone-200">
                            @foreach($uploads as $upload)
                                <div class="py-5 first:pt-0 last:pb-0" @if($upload['id'] === 'pass_hero_image') x-show="cardDesign === 'classic'" @endif>
                                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                        <label for="{{ $upload['id'] }}" class="font-semibold text-stone-900">{{ $upload['label'] }} <span class="text-xs font-normal text-stone-500">Required</span></label>
                                        <span class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700" x-show="{{ $upload['preview'] }}" x-cloak>Image added</span>
                                    </div>
                                    <p id="{{ $upload['id'] }}-hint" class="mb-3 text-sm text-stone-500">{{ $upload['hint'] }}</p>
                                    <div class="flex min-h-24 items-center gap-4 rounded-xl border border-dashed border-stone-300 bg-stone-50 p-4">
                                        <div class="flex h-20 w-28 shrink-0 items-center justify-center rounded-lg border border-stone-200 bg-white">
                                            <img x-show="{{ $upload['preview'] }}" :src="{{ $upload['preview'] }}" alt="{{ $upload['label'] }} preview" class="{{ $upload['shape'] }} rounded p-1">
                                            <span x-show="!{{ $upload['preview'] }}" class="text-2xl text-stone-400" aria-hidden="true">＋</span>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <label class="inline-flex min-h-11 cursor-pointer items-center justify-center rounded-lg border border-stone-300 bg-white px-3 text-sm font-semibold text-brand-700 focus-within:ring-2 focus-within:ring-brand-500">
                                                <span x-text="{{ $upload['preview'] }} ? 'Replace image' : 'Choose image'">Choose image</span>
                                                <input type="file" id="{{ $upload['id'] }}" name="{{ $upload['id'] }}" @required(blank($store->{$upload['path']}) && (old('card_design', \App\Support\CardDesign::style($store)) === 'classic' || $upload['id'] !== 'pass_hero_image')) :required="{{ blank($store->{$upload['path']}) ? 'true' : 'false' }} && (cardDesign === 'classic' || '{{ $upload['id'] }}' !== 'pass_hero_image')" x-ref="{{ $upload['ref'] }}" accept="image/png,image/jpeg,image/jpg,image/webp" aria-describedby="{{ $upload['id'] }}-hint" class="sr-only">
                                            </label>
                                            <p class="mt-2 break-all text-xs text-stone-500" x-text="{{ $upload['preview'] }} ? ($refs.{{ $upload['ref'] }}?.files[0]?.name || 'Saved image') : 'No image selected'"></p>
                                        </div>
                                    </div>
                                    <x-input-error :messages="$errors->get($upload['id'])" class="mt-2" />
                                </div>
                            @endforeach
                        </div>
                    </section>
                </div>

                <aside id="onboarding-card-preview" class="min-w-0 scroll-mt-36 lg:col-span-2">
                    <div class="rounded-2xl border border-stone-200 bg-stone-50 p-4 lg:sticky lg:top-36">
                        <h2 class="mb-3 text-lg font-semibold text-stone-900">Card preview</h2>
                        <x-wallet-pass-preview :preview-data="$walletPreview ?? []" />
                        <p class="mt-3 text-sm text-stone-500">Sample card. Check both Wallet previews before continuing.</p>
                    </div>
                </aside>
            </div>
            <div class="mt-6 rounded-2xl border border-stone-200 bg-white p-5">
                <p x-show="!hasRequiredAssets" class="mb-3 text-sm text-stone-600">Add your logos and any photo or custom icon required by your style to continue. Saved images are kept unless you replace them.</p>
                <p x-show="hasBlockedContrast" x-cloak class="mb-3 text-sm text-red-700">Choose more distinct accent and background colours to continue.</p>
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <a href="{{ route('merchant.onboarding.wizard.store-basics') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl px-4 text-sm font-semibold text-stone-600">Back to reward setup</a>
                    <x-ui.button type="submit" variant="primary" size="lg" class="min-h-11 w-full sm:w-auto" x-bind:disabled="hasBlockedContrast || !hasRequiredAssets">Save and continue → Customer signup</x-ui.button>
                </div>
            </div>
        </form>
    </x-onboarding-step-layout>
</x-onboarding-layout>
