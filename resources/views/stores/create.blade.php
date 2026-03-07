@php
    $inputClass = 'block w-full rounded-xl border border-stone-300 bg-white px-4 py-2.5 text-sm text-stone-900 placeholder-stone-400 transition-colors focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500';
    $brandColor = old('brand_color', '#0EA5E9');
    $bgColor = old('background_color', '#1F2937');
    $rewardTarget = (int) old('reward_target', 9);
    $rewardTitle = old('reward_title', 'Free coffee');
    $storeName = old('name', '');
@endphp

<x-merchant-layout>
    <x-slot name="header">Create Store</x-slot>

    <div class="min-h-[calc(100vh-4rem)] bg-gradient-to-b from-stone-100 to-stone-50/80 -mx-4 sm:-mx-6 lg:-mx-8 -mb-6 px-4 sm:px-6 lg:px-8 py-8 sm:py-10">
        <div class="max-w-5xl mx-auto">
            <div class="bg-white rounded-2xl shadow-xl shadow-stone-200/50 border border-stone-200/80 overflow-hidden">
                <div class="p-6 sm:p-8 lg:p-10">
                    <header class="mb-8">
                        <div class="mb-4">
                            <a href="{{ route('merchant.stores.index') }}" class="inline-flex items-center gap-2 text-sm font-medium text-stone-600 hover:text-stone-900 transition-colors focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 rounded-lg px-2 py-1 -ml-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                Back to stores
                            </a>
                        </div>
                        <h2 class="text-2xl sm:text-3xl font-bold text-stone-900 tracking-tight">Create a new store</h2>
                        <p class="mt-3 text-base text-stone-600 leading-relaxed">Use the same setup flow as onboarding to add another store without changing how the app works.</p>
                    </header>

                    <form method="POST" action="{{ route('merchant.stores.store') }}" enctype="multipart/form-data" id="store-create-form"
                        x-data="{
                            storeName: @js($storeName),
                            rewardTarget: {{ $rewardTarget }},
                            rewardTitle: @js($rewardTitle),
                            brandColor: @js($brandColor),
                            bgColor: @js($bgColor),
                            logoPreview: null,
                            passLogoPreview: null,
                            passHeroPreview: null
                        }"
                        x-init="
                            $refs.brandColor.addEventListener('input', e => { brandColor = e.target.value; $refs.brandColorText.value = e.target.value; });
                            $refs.brandColorText.addEventListener('input', e => { if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) { brandColor = e.target.value; $refs.brandColor.value = e.target.value; } });
                            $refs.bgColor.addEventListener('input', e => { bgColor = e.target.value; $refs.bgColorText.value = e.target.value; });
                            $refs.bgColorText.addEventListener('input', e => { if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) { bgColor = e.target.value; $refs.bgColor.value = e.target.value; } });
                            $refs.logoInput.addEventListener('change', e => { const f = e.target.files[0]; logoPreview = f ? URL.createObjectURL(f) : null; });
                            $refs.passLogoInput.addEventListener('change', e => { const f = e.target.files[0]; passLogoPreview = f ? URL.createObjectURL(f) : null; });
                            $refs.passHeroInput.addEventListener('change', e => { const f = e.target.files[0]; passHeroPreview = f ? URL.createObjectURL(f) : null; });
                        ">
                        @csrf
                        <x-form-error-summary form-id="store-create-form" />

                        <div class="grid grid-cols-1 sm:grid-cols-5 gap-6">
                            <div class="sm:col-span-3 space-y-8">
                                <x-onboarding-form-section title="Store details">
                                    <div class="space-y-5">
                                        <div>
                                            <label for="name" class="block text-sm font-medium text-stone-700 mb-1.5">Store name</label>
                                            <x-ui.input type="text" id="name" name="name" x-model="storeName" value="{{ $storeName }}" class="{{ $inputClass }}" required />
                                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                        </div>
                                        <div>
                                            <label for="address" class="block text-sm font-medium text-stone-700 mb-1.5">Address <span class="text-stone-400 font-normal">(optional)</span></label>
                                            <x-ui.input type="text" id="address" name="address" value="{{ old('address') }}" class="{{ $inputClass }}" />
                                            <x-input-error :messages="$errors->get('address')" class="mt-2" />
                                        </div>
                                    </div>
                                </x-onboarding-form-section>

                                <x-onboarding-form-section title="Reward setup">
                                    <div class="space-y-5">
                                        <div>
                                            <label for="reward_target" class="block text-sm font-medium text-stone-700 mb-1.5">Stamps needed for reward</label>
                                            <input type="number" id="reward_target" name="reward_target" x-model.number="rewardTarget" value="{{ $rewardTarget }}" min="1" class="{{ $inputClass }}" required />
                                            <x-onboarding-helper-note>Most cafés use 8–10 stamps.</x-onboarding-helper-note>
                                            <x-input-error :messages="$errors->get('reward_target')" class="mt-2" />
                                        </div>
                                        <div>
                                            <label for="reward_title" class="block text-sm font-medium text-stone-700 mb-1.5">Reward title</label>
                                            <input type="text" id="reward_title" name="reward_title" x-model="rewardTitle" value="{{ $rewardTitle }}" class="{{ $inputClass }}" placeholder="e.g. Free coffee" required />
                                            <x-onboarding-helper-note>e.g. “Free regular coffee”, “Free pastry”</x-onboarding-helper-note>
                                            <x-input-error :messages="$errors->get('reward_title')" class="mt-2" />
                                        </div>
                                    </div>
                                </x-onboarding-form-section>

                                <x-onboarding-form-section title="Brand colors">
                                    <div class="space-y-5">
                                        <div>
                                            <label for="brand_color" class="block text-sm font-medium text-stone-700 mb-1.5">Brand color</label>
                                            <div class="flex gap-3 mt-1.5">
                                                <input type="color" id="brand_color" name="brand_color" x-ref="brandColor" value="{{ $brandColor }}" class="h-11 w-14 rounded-xl border border-stone-300 cursor-pointer flex-shrink-0 bg-white" />
                                                <input type="text" id="brand_color_text" x-ref="brandColorText" value="{{ $brandColor }}" placeholder="#0EA5E9" autocapitalize="off" spellcheck="false" class="{{ $inputClass }}" />
                                            </div>
                                            <x-onboarding-helper-note>Used for buttons and accents on the customer card.</x-onboarding-helper-note>
                                            <x-input-error :messages="$errors->get('brand_color')" class="mt-2" />
                                        </div>
                                        <div>
                                            <label for="background_color" class="block text-sm font-medium text-stone-700 mb-1.5">Background color</label>
                                            <div class="flex gap-3 mt-1.5">
                                                <input type="color" id="background_color" name="background_color" x-ref="bgColor" value="{{ $bgColor }}" class="h-11 w-14 rounded-xl border border-stone-300 cursor-pointer flex-shrink-0 bg-white" />
                                                <input type="text" id="background_color_text" x-ref="bgColorText" value="{{ $bgColor }}" placeholder="#1F2937" autocapitalize="off" spellcheck="false" class="{{ $inputClass }}" />
                                            </div>
                                            <x-onboarding-helper-note>Background of the join page and card.</x-onboarding-helper-note>
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
                                    <p class="text-sm text-stone-600 mb-4">For Apple Wallet and Google Wallet passes. Skip if you do not use wallet passes yet.</p>
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
                                <div class="sticky top-8 space-y-4">
                                    <x-wallet-pass-preview />
                                    <div class="rounded-xl bg-stone-50/80 border border-stone-200/80 p-5">
                                        <p class="text-sm font-semibold text-stone-800">What merchants are seeing</p>
                                        <ul class="mt-3 space-y-2 text-sm text-stone-600">
                                            <li>Apple Wallet-style front card mock</li>
                                            <li>Live updates from colors, logo, and wallet assets</li>
                                            <li>Closer preview of the real pass before saving</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="px-6 sm:px-8 lg:px-10 py-5 bg-stone-50/90 border-t border-stone-200">
                    <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3">
                        <a href="{{ route('merchant.stores.index') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 text-sm font-medium text-stone-600 hover:text-stone-900 transition-colors focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 rounded-lg py-2 px-3">
                            Cancel
                        </a>
                        <x-ui.button type="submit" form="store-create-form" variant="primary" size="lg" class="w-full sm:w-auto rounded-xl min-w-[160px]">Create store</x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-merchant-layout>
