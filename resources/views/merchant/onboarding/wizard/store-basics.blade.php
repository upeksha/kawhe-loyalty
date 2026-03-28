@php
    $inputClass = 'block w-full rounded-xl border border-stone-300 bg-white px-4 py-2.5 text-sm text-stone-900 placeholder-stone-400 transition-colors focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500';
@endphp
<x-merchant-layout>
    <x-slot name="header">Set up your store</x-slot>

    <x-onboarding-step-layout
        :step="1"
        :totalSteps="5"
        title="Create your first loyalty card"
        subtitle="Start with the basics for your café's rewards program."
    >
@php
    $defaultRewardTarget = (int) old('reward_target', $store?->reward_target ?? 9);
    $defaultRewardTitle = old('reward_title', $store?->reward_title ?? 'Free coffee');
@endphp
        <form method="POST" action="{{ route('merchant.onboarding.wizard.store-basics.store') }}" id="store-basics-form" x-data="{ storeName: {{ json_encode(old('name', $store?->name ?? '')) }}, rewardTarget: {{ $defaultRewardTarget }}, rewardTitle: {{ json_encode($defaultRewardTitle) }} }">
            @csrf
            <x-form-error-summary form-id="store-basics-form" />
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <div class="sm:col-span-2 space-y-8">
                    <x-onboarding-form-section title="Store details">
                        <div class="space-y-5">
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
                    </x-onboarding-form-section>

                    <x-onboarding-form-section title="Reward setup">
                        <div class="space-y-5">
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
                        <div class="rounded-2xl border border-stone-200 bg-stone-50/80 p-5 shadow-sm mb-4">
                            <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Setup status</p>
                            <ul class="mt-4 space-y-3 text-sm">
                                <li class="flex items-start justify-between gap-3">
                                    <span class="text-stone-700">Store name added</span>
                                    <span class="font-medium" :class="storeName?.trim() ? 'text-emerald-700' : 'text-stone-500'" x-text="storeName?.trim() ? 'Ready' : 'Needed'"></span>
                                </li>
                                <li class="flex items-start justify-between gap-3">
                                    <span class="text-stone-700">Reward target set</span>
                                    <span class="font-medium" :class="rewardTarget > 0 ? 'text-emerald-700' : 'text-stone-500'" x-text="rewardTarget > 0 ? 'Ready' : 'Needed'"></span>
                                </li>
                                <li class="flex items-start justify-between gap-3">
                                    <span class="text-stone-700">Reward title named</span>
                                    <span class="font-medium" :class="rewardTitle?.trim() ? 'text-emerald-700' : 'text-stone-500'" x-text="rewardTitle?.trim() ? 'Ready' : 'Needed'"></span>
                                </li>
                            </ul>
                            <p class="mt-4 text-xs leading-relaxed text-stone-500">Once these are ready, the next step is branding and wallet styling.</p>
                        </div>
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
</x-merchant-layout>
