@php
    $inputClass = 'block w-full rounded-xl border border-stone-300 bg-white px-4 py-2.5 text-sm text-stone-900 placeholder-stone-400 transition-colors focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500';
@endphp
<x-onboarding-layout>
    <x-slot name="header">Set up your reward</x-slot>

    <x-onboarding-step-layout
        :step="1"
        :totalSteps="4"
        title="Set up your reward"
        subtitle="You already added your store at signup. Choose how many stamps customers need and what they earn — you can change branding and signup fields in the next steps."
    >
@php
    $defaultRewardTarget = (int) old('reward_target', $store?->reward_target ?? 9);
    $defaultRewardTitle = old('reward_title', $store?->reward_title ?? 'Free coffee');
    $hasPrefilledStore = filled(old('name', $store?->name ?? ''));
@endphp
        <form method="POST" action="{{ route('merchant.onboarding.wizard.store-basics.store') }}" id="store-basics-form" x-data="{
            storeName: {{ json_encode(old('name', $store?->name ?? '')) }},
            rewardTarget: {{ $defaultRewardTarget }},
            rewardTitle: {{ json_encode($defaultRewardTitle) }},
            showStoreDetails: {{ $hasPrefilledStore ? 'false' : 'true' }},
            applyPreset(preset) {
                if (preset === 'classic') {
                    this.rewardTarget = 9;
                    this.rewardTitle = 'Free coffee';
                } else if (preset === 'repeat-visits') {
                    this.rewardTarget = 6;
                    this.rewardTitle = 'Free regular coffee';
                } else if (preset === 'higher-ticket') {
                    this.rewardTarget = 10;
                    this.rewardTitle = 'Free brunch item';
                }
            }
        }">
            @csrf
            <x-form-error-summary form-id="store-basics-form" />
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <div class="sm:col-span-2 space-y-8">
                    <x-onboarding-form-section title="Your store">
                        <div class="rounded-xl border border-stone-200 bg-stone-50/80 overflow-hidden">
                            <button
                                type="button"
                                @click="showStoreDetails = !showStoreDetails"
                                class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition hover:bg-stone-100/80"
                            >
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-stone-800" x-text="storeName?.trim() ? storeName : 'Add store name'"></p>
                                    <p class="text-xs text-stone-500 mt-0.5">From signup — tap to edit name or address</p>
                                </div>
                                <svg class="w-5 h-5 text-stone-400 shrink-0 transition-transform" :class="{ 'rotate-180': showStoreDetails }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="showStoreDetails" x-transition class="border-t border-stone-200 px-4 py-4 space-y-4">
                                <div>
                                    <label for="name" class="block text-sm font-medium text-stone-700 mb-1.5">Store name</label>
                                    <x-ui.input type="text" id="name" name="name" x-model="storeName" value="{{ old('name', $store?->name ?? '') }}" class="{{ $inputClass }}" required />
                                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                </div>
                                <div>
                                    <label for="address" class="block text-sm font-medium text-stone-700 mb-1.5">Address <span class="text-stone-400 font-normal">(optional)</span></label>
                                    <x-ui.input type="text" id="address" name="address" value="{{ old('address', $store?->address ?? '') }}" class="{{ $inputClass }}" />
                                    <x-input-error :messages="$errors->get('address')" class="mt-2" />
                                </div>
                            </div>
                        </div>
                    </x-onboarding-form-section>

                    <x-onboarding-form-section title="Reward setup">
                        <div class="space-y-5">
                            <div>
                                <p class="block text-sm font-medium text-stone-700 mb-2">Quick presets</p>
                                <div class="flex flex-wrap gap-2">
                                    <x-ui.chip-button type="button" @click="applyPreset('classic')">Classic coffee card</x-ui.chip-button>
                                    <x-ui.chip-button type="button" @click="applyPreset('repeat-visits')">Fast repeat visits</x-ui.chip-button>
                                    <x-ui.chip-button type="button" @click="applyPreset('higher-ticket')">Higher-ticket reward</x-ui.chip-button>
                                </div>
                                <p class="mt-2 text-xs text-stone-500">These only fill the fields for you. You can still customise everything before saving.</p>
                            </div>
                            <div>
                                <label for="reward_target" class="block text-sm font-medium text-stone-700 mb-1.5">Stamps needed for reward</label>
                                <input type="number" id="reward_target" name="reward_target" x-model.number="rewardTarget" value="{{ old('reward_target', $store?->reward_target ?? 9) }}" min="1" class="{{ $inputClass }}" required />
                                <p class="mt-1.5 text-xs text-stone-500">Most cafés use 8–10 stamps.</p>
                                <x-input-error :messages="$errors->get('reward_target')" class="mt-2" />
                            </div>
                            <div>
                                <label for="reward_title" class="block text-sm font-medium text-stone-700 mb-1.5">Reward title</label>
                                <input type="text" id="reward_title" name="reward_title" x-model="rewardTitle" value="{{ old('reward_title', $store?->reward_title ?? 'Free coffee') }}" class="{{ $inputClass }}" placeholder="e.g. Free coffee" required />
                                <p class="mt-1.5 text-xs text-stone-500">e.g. “Free regular coffee”, “Free pastry”</p>
                                <x-input-error :messages="$errors->get('reward_title')" class="mt-2" />
                            </div>
                            <div class="rounded-xl border border-stone-200 bg-stone-50/80 p-4">
                                <p class="text-sm font-semibold text-stone-800">Best practice</p>
                                <p class="mt-1 text-sm leading-relaxed text-stone-600">
                                    Short, concrete rewards usually convert better than broad ones. “Free regular coffee” or “Free pastry” is easier for customers and staff than a vague reward name.
                                </p>
                            </div>
                        </div>
                    </x-onboarding-form-section>
                </div>

                <div class="sm:col-span-1">
                    <details class="sm:hidden rounded-xl border border-stone-200 bg-stone-50/70 p-4">
                        <summary class="cursor-pointer list-none flex items-center justify-between text-sm font-semibold text-stone-800">
                            Card summary preview
                            <span class="text-xs font-medium text-stone-500">Tap to expand</span>
                        </summary>
                        <div class="mt-4 rounded-2xl bg-white border border-stone-200 p-6 shadow-sm" role="status" aria-live="polite">
                            <p class="text-lg font-semibold text-stone-800 leading-snug" x-text="'Buy ' + (rewardTarget || 9) + ', get 1 free'"></p>
                            <p class="text-stone-600 mt-1" x-text="(rewardTitle || 'reward').toLowerCase()"></p>
                            <p class="text-xs text-stone-400 mt-4 pt-4 border-t border-stone-100">Stamp card summary</p>
                        </div>
                    </details>
                    <div class="hidden sm:block sticky top-8">
                        <p class="text-xs font-semibold text-stone-500 uppercase tracking-wider mb-3">What customers will see</p>
                        <div class="rounded-2xl bg-white border border-stone-200 p-6 shadow-lg shadow-stone-200/30" role="status" aria-live="polite">
                            <p class="text-lg font-semibold text-stone-800 leading-snug" x-text="'Buy ' + (rewardTarget || 9) + ', get 1 free'"></p>
                            <p class="text-stone-600 mt-1" x-text="(rewardTitle || 'reward').toLowerCase()"></p>
                            <p class="text-xs text-stone-400 mt-4 pt-4 border-t border-stone-100">Stamp card summary</p>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <x-slot name="actions">
            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                <x-ui.button type="submit" form="store-basics-form" variant="primary" size="lg" class="w-full sm:w-auto min-w-[140px] rounded-xl">Continue</x-ui.button>
            </div>
        </x-slot>
    </x-onboarding-step-layout>
</x-onboarding-layout>
