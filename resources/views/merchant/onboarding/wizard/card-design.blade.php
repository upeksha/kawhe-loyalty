@php
    $brandColor = old('brand_color', $store->brand_color ?? '#0EA5E9');
    $defaultBgColor = $store->background_color ?? '#263E30';
    if ($store->onboarding_step === \App\Models\Store::ONBOARDING_STEP_CARD_DESIGN
        && !$store->logo_path && !$store->pass_logo_path
        && strtoupper($defaultBgColor) === \App\Models\Store::DEFAULT_BACKGROUND_COLOR) {
        $defaultBgColor = '#263E30';
    }
    $bgColor = old('background_color', $defaultBgColor);
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
                <x-card-appearance-fields :model="$store" :brand-color="$brandColor" :bg-color="$bgColor" require-logos />
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
