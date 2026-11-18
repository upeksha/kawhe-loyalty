@php
    $brandColor = old('brand_color', $store->brand_color ?? '#0EA5E9');
    $bgColor = old('background_color', $store->background_color ?? '#1F2937');
@endphp
<x-onboarding-layout class="kawhe-card-editor">
    <x-slot name="header">Setup · Create your card</x-slot>

    <x-onboarding-step-layout
        :step="3"
        compact-progress
        :store="$store"
        title="Design your loyalty card"
        subtitle="Choose a card style, colours and the icon customers collect."
    >
        <x-slot:preview>
            <div id="onboarding-card-preview" class="scroll-mt-6 space-y-3 text-center">
                <h2 class="text-[28px] font-bold leading-tight">Card preview</h2>
                <x-wallet-pass-preview :preview-data="$walletPreview ?? []" :source-wallet-logo="$store->pass_logo_url ?? $store->resolvedDefaultProgram()?->pass_logo_url" onboarding />
                <p class="mx-auto max-w-[23rem] text-sm leading-tight">Sample Wallet pass. QR and manual code are for preview only.</p>
            </div>
        </x-slot:preview>
        <form method="POST" action="{{ route('merchant.onboarding.wizard.card-design.store') }}" enctype="multipart/form-data" id="card-design-form" class="kawhe-card-design-form"
        x-data="{
            cardDesign: @js(\App\Support\CardDesign::formStyle($store)),
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
            $refs.brandColorText && $refs.brandColorText.addEventListener('input', e => { if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) { brandColor = e.target.value; if ($refs.brandColor) { $refs.brandColor.value = e.target.value; $refs.brandColor.dispatchEvent(new Event('input', { bubbles: true })); } } });
            $refs.bgColor && $refs.bgColor.addEventListener('input', e => { bgColor = e.target.value; $refs.bgColorText && ($refs.bgColorText.value = e.target.value); });
            $refs.bgColorText && $refs.bgColorText.addEventListener('input', e => { if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) { bgColor = e.target.value; if ($refs.bgColor) { $refs.bgColor.value = e.target.value; $refs.bgColor.dispatchEvent(new Event('input', { bubbles: true })); } } });
            $refs.logoInput && $refs.logoInput.addEventListener('change', e => { const f = e.target.files[0]; if (f) logoPreview = URL.createObjectURL(f); });
            $refs.passLogoInput && $refs.passLogoInput.addEventListener('change', e => { const f = e.target.files[0]; if (f) passLogoPreview = URL.createObjectURL(f); });
            $refs.passHeroInput && $refs.passHeroInput.addEventListener('change', e => { const f = e.target.files[0]; if (f) passHeroPreview = URL.createObjectURL(f); });
        "
        >
            @csrf
            <div class="space-y-3.5">
                <x-form-error-summary form-id="card-design-form" />
                <a href="#onboarding-card-preview" class="block text-sm font-semibold text-brand-700 underline min-[900px]:hidden">See card preview ↓</a>
                <x-card-design-choice :model="$store" section="style" visual compact />

                <section class="kawhe-editor-section">
                    <div x-show="cardDesign === 'classic'" x-cloak class="mb-3">
                        <label for="brand_color" class="block text-sm font-semibold">Accent colour</label>
                        <div class="mt-1.5 flex items-center gap-2">
                            <input type="color" id="brand_color" name="brand_color" x-ref="brandColor" value="{{ $brandColor }}" class="color-swatch-input size-8 cursor-pointer rounded-full border border-stone-300 p-0">
                            <input type="text" id="brand_color_text" :disabled="cardDesign !== 'classic'" x-ref="brandColorText" value="{{ $brandColor }}" aria-label="Accent colour hex code" required pattern="#[0-9A-Fa-f]{6}" maxlength="7" class="rounded-lg border-stone-300 px-2 py-1 text-sm">
                        </div>
                        <x-input-error :messages="$errors->get('brand_color')" class="mt-2" />
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <label for="background_color_text" class="text-[15px] font-semibold">Card colour</label>
                        <input type="text" id="background_color_text" x-ref="bgColorText" value="{{ $bgColor }}" aria-label="Background colour hex code" required pattern="#[0-9A-Fa-f]{6}" maxlength="7" title="Enter a colour code such as #263E30" autocapitalize="off" spellcheck="false" class="w-20 rounded border-0 bg-transparent p-0 text-right text-xs font-semibold uppercase text-stone-600 focus:ring-brand-500">
                    </div>
                    <x-card-colour-presets visual compact :background="$bgColor" />
                    <x-input-error :messages="$errors->get('background_color')" class="mt-2" />
                    <div x-show="hasBlockedContrast" x-cloak>
                        <x-ui.alert variant="danger">
                            <p class="font-semibold">Choose more distinct colours</p>
                            <p class="mt-1 leading-relaxed">
                                Your brand and background colors are too close together, so the join page and saved card can become unreadable. Use a darker background, a brighter accent, or increase the contrast between them before continuing.
                            </p>
                        </x-ui.alert>
                    </div>
                    <div x-show="cardDesign === 'classic' && !hasBlockedContrast && (hasLowContrastPreview || hasVeryLightBackground)" x-cloak>
                        <x-ui.alert variant="warning">
                            <p class="font-semibold">Check your colours</p>
                            <p class="mt-1 leading-relaxed">
                                This combination will save, but it may still look washed out on the join page or wallet handoff. The safest fix is a darker background and a more distinct accent color.
                            </p>
                        </x-ui.alert>
                    </div>
                </section>

                <x-card-design-choice :model="$store" section="stamp" visual compact />
                <x-stamp-appearance :model="$store" compact />
                <x-card-text-colour :model="$store" compact />
                <fieldset>
                    <legend class="sr-only">Card logos</legend>
                    @php
                        $uploads = [
                            ['id' => 'logo', 'label' => 'Store logo', 'ref' => 'logoInput', 'preview' => 'logoPreview', 'path' => 'logo_path'],
                            ['id' => 'pass_logo', 'label' => 'Wallet logo', 'ref' => 'passLogoInput', 'preview' => 'passLogoPreview', 'path' => 'pass_logo_path'],
                            ['id' => 'pass_hero_image', 'label' => 'Wallet banner', 'ref' => 'passHeroInput', 'preview' => 'passHeroPreview', 'path' => 'pass_hero_image_path'],
                        ];
                    @endphp
                    <div class="grid gap-2.5 min-[480px]:grid-cols-2">
                        @foreach($uploads as $upload)
                            @php($classicOnly = $upload['id'] === 'pass_hero_image')
                            <div class="min-w-0" @if($classicOnly) x-show="cardDesign === 'classic'" x-cloak @endif>
                                <label for="{{ $upload['id'] }}" class="mb-1 block text-sm font-semibold">{{ $upload['label'] }} (required)</label>
                                <label class="kawhe-editor-upload focus-within:ring-2 focus-within:ring-brand-600">
                                    <span class="min-w-0 truncate" x-text="$refs.{{ $upload['ref'] }}?.files[0]?.name || ({{ $upload['preview'] }} ? @js($upload['label'].' added') : 'No image selected')"></span>
                                    <span class="shrink-0 text-[13px] font-semibold text-brand-800" aria-hidden="true" x-text="{{ $upload['preview'] }} ? 'Replace' : 'Choose'"></span>
                                    <input type="file" id="{{ $upload['id'] }}" name="{{ $upload['id'] }}" x-ref="{{ $upload['ref'] }}"
                                        :required="{{ blank($store->{$upload['path']}) ? 'true' : 'false' }} && ({{ $classicOnly ? "cardDesign === 'classic'" : 'true' }})"
                                        @if($classicOnly) :disabled="cardDesign !== 'classic'" @endif
                                        accept="image/png,image/jpeg,image/webp" aria-describedby="card-upload-hint" class="sr-only">
                                </label>
                                <x-input-error :messages="$errors->get($upload['id'])" class="mt-2" />
                            </div>
                        @endforeach
                    </div>
                    <p id="card-upload-hint" class="mt-3 text-xs text-stone-600">PNG, JPG or WebP, up to 2 MB each. Saved images are kept unless replaced.</p>
                </fieldset>
            </div>

            <div class="mt-auto pt-6">
                <div class="space-y-2 border-t border-stone-200 pt-3">
                    <p x-show="hasBlockedContrast" x-cloak class="text-sm text-red-700">Choose more distinct accent and background colours to continue.</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('merchant.onboarding.wizard.store-basics') }}" class="inline-flex min-h-9 items-center rounded-lg px-3 text-sm font-semibold text-brand-800">Back</a>
                        <button type="submit" class="kawhe-editor-next" :disabled="hasBlockedContrast || !hasRequiredAssets">
                            Save and continue
                            <img src="{{ asset('images/onboarding/continue-arrow.svg') }}" alt="">
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </x-onboarding-step-layout>
</x-onboarding-layout>
