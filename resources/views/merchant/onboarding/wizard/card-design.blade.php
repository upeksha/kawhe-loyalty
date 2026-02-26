@php
    $brandColor = old('brand_color', $store->brand_color ?? '#0EA5E9');
    $bgColor = old('background_color', $store->background_color ?? '#1F2937');
    $inputClass = 'block w-full rounded-xl border border-stone-300 bg-white px-4 py-2.5 text-sm text-stone-900 placeholder-stone-400 transition-colors focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500';
@endphp
<x-merchant-layout>
    <x-slot name="header">Card design</x-slot>

    <x-onboarding-step-layout
        :step="2"
        :totalSteps="5"
        title="Make it yours"
        subtitle="Brand colors and logo for your customer card and join page. Optional: add images for Apple &amp; Google Wallet."
        :backUrl="route('merchant.onboarding.wizard.store-basics')"
    >
        <form method="POST" action="{{ route('merchant.onboarding.wizard.card-design.store') }}" enctype="multipart/form-data" id="card-design-form"
        x-data="{
            brandColor: '{{ $brandColor }}',
            bgColor: '{{ $bgColor }}',
            logoPreview: '{{ $store->logo_path ? asset('storage/' . $store->logo_path) : '' }}',
            passLogoPreview: '{{ $store->pass_logo_path ? asset('storage/' . $store->pass_logo_path) : '' }}',
            passHeroPreview: '{{ $store->pass_hero_image_path ? asset('storage/' . $store->pass_hero_image_path) : '' }}'
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
                                    <input type="color" id="brand_color" name="brand_color" x-ref="brandColor" value="{{ $brandColor }}" class="h-11 w-14 rounded-xl border border-stone-300 cursor-pointer flex-shrink-0 bg-white" />
                                    <input type="text" id="brand_color_text" x-ref="brandColorText" value="{{ $brandColor }}" placeholder="#0EA5E9" aria-label="Brand color hex value" aria-describedby="brand_color_note" autocapitalize="off" spellcheck="false" class="{{ $inputClass }}" />
                                </div>
                                <x-onboarding-helper-note id="brand_color_note">Used for buttons and accents on the customer card.</x-onboarding-helper-note>
                                <x-input-error :messages="$errors->get('brand_color')" class="mt-2" />
                            </div>
                            <div>
                                <label for="background_color" class="block text-sm font-medium text-stone-700 mb-1.5">Background color</label>
                                <div class="flex gap-3 mt-1.5">
                                    <input type="color" id="background_color" name="background_color" x-ref="bgColor" value="{{ $bgColor }}" class="h-11 w-14 rounded-xl border border-stone-300 cursor-pointer flex-shrink-0 bg-white" />
                                    <input type="text" id="background_color_text" x-ref="bgColorText" value="{{ $bgColor }}" placeholder="#1F2937" aria-label="Background color hex value" aria-describedby="background_color_note" autocapitalize="off" spellcheck="false" class="{{ $inputClass }}" />
                                </div>
                                <x-onboarding-helper-note id="background_color_note">Background of the join page and card.</x-onboarding-helper-note>
                                <x-input-error :messages="$errors->get('background_color')" class="mt-2" />
                            </div>
                        </div>
                    </x-onboarding-form-section>

                    <x-onboarding-form-section title="Store branding">
                        <div>
                            <label for="logo" class="block text-sm font-medium text-stone-700 mb-1.5">Store logo <span class="text-stone-400 font-normal">(optional)</span></label>
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
                                </div>
                            </div>
                            <x-input-error :messages="$errors->get('logo')" class="mt-2" />
                        </div>
                    </x-onboarding-form-section>

                    <x-onboarding-form-section title="Wallet assets" class="relative">
                        <span class="absolute -top-1 right-0 text-xs font-medium text-stone-500 bg-stone-100 px-2.5 py-1 rounded-lg">Optional</span>
                        <p class="text-sm text-stone-600 mb-4">For Apple Wallet and Google Wallet passes. Skip if you don’t use wallet passes yet.</p>
                        <div class="space-y-5">
                            <div>
                                <label for="pass_logo" class="block text-sm font-medium text-stone-700 mb-1.5">Pass logo</label>
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
                                <label for="pass_hero_image" class="block text-sm font-medium text-stone-700 mb-1.5">Pass hero image</label>
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
                            Card preview
                            <span class="text-xs font-medium text-stone-500">Tap to expand</span>
                        </summary>
                        <div class="mt-4 rounded-2xl overflow-hidden border border-stone-200 shadow-sm" :style="{ backgroundColor: bgColor }" role="status" aria-live="polite">
                            <div class="p-5 text-white">
                                <div class="flex items-center gap-3 mb-3">
                                    <template x-if="logoPreview">
                                        <img :src="logoPreview" alt="" class="h-10 w-10 object-contain rounded-lg bg-white/20 p-1">
                                    </template>
                                    <span class="font-semibold text-lg" x-text="'{{ addslashes($store->name) }}'"></span>
                                </div>
                                <p class="text-white/90 text-sm" x-text="'{{ addslashes($store->reward_title) }}'"></p>
                                <div class="flex gap-1.5 mt-4 flex-wrap">
                                    <span class="w-6 h-6 rounded-full border-2" :style="{ borderColor: brandColor }"></span>
                                    <span class="w-6 h-6 rounded-full border-2" :style="{ borderColor: brandColor }"></span>
                                    <span class="w-6 h-6 rounded-full border-2" :style="{ borderColor: brandColor }"></span>
                                    <span class="w-6 h-6 rounded-full border-2 border-white/30"></span>
                                    <span class="w-6 h-6 rounded-full border-2 border-white/30"></span>
                                    <span class="w-6 h-6 rounded-full border-2 border-white/30"></span>
                                    <span class="w-6 h-6 rounded-full border-2 border-white/30"></span>
                                    <span class="w-6 h-6 rounded-full border-2 border-white/30"></span>
                                    <span class="w-6 h-6 rounded-full border-2 border-white/30"></span>
                                </div>
                            </div>
                        </div>
                    </details>
                    <div class="hidden sm:block sticky top-8">
                        <p class="text-xs font-semibold text-stone-500 uppercase tracking-wider mb-3">Preview</p>
                        <div class="rounded-2xl overflow-hidden border border-stone-200 shadow-xl shadow-stone-200/40" :style="{ backgroundColor: bgColor }" role="status" aria-live="polite">
                            <div class="p-5 text-white">
                                <div class="flex items-center gap-3 mb-3">
                                    <template x-if="logoPreview">
                                        <img :src="logoPreview" alt="" class="h-10 w-10 object-contain rounded-lg bg-white/20 p-1">
                                    </template>
                                    <span class="font-semibold text-lg" x-text="'{{ addslashes($store->name) }}'"></span>
                                </div>
                                <p class="text-white/90 text-sm" x-text="'{{ addslashes($store->reward_title) }}'"></p>
                                <div class="flex gap-1.5 mt-4 flex-wrap">
                                    <span class="w-6 h-6 rounded-full border-2" :style="{ borderColor: brandColor }"></span>
                                    <span class="w-6 h-6 rounded-full border-2" :style="{ borderColor: brandColor }"></span>
                                    <span class="w-6 h-6 rounded-full border-2" :style="{ borderColor: brandColor }"></span>
                                    <span class="w-6 h-6 rounded-full border-2 border-white/30"></span>
                                    <span class="w-6 h-6 rounded-full border-2 border-white/30"></span>
                                    <span class="w-6 h-6 rounded-full border-2 border-white/30"></span>
                                    <span class="w-6 h-6 rounded-full border-2 border-white/30"></span>
                                    <span class="w-6 h-6 rounded-full border-2 border-white/30"></span>
                                    <span class="w-6 h-6 rounded-full border-2 border-white/30"></span>
                                </div>
                            </div>
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
                <x-ui.button type="submit" form="card-design-form" variant="primary" size="lg" class="w-full sm:w-auto rounded-xl min-w-[140px]">Continue</x-ui.button>
            </div>
        </x-slot>
    </x-onboarding-step-layout>
</x-merchant-layout>
