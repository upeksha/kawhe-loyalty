@php
    $inputClass = 'block w-full min-h-11 rounded-xl border border-stone-300 bg-white px-4 py-2.5 text-base text-stone-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500';
    $defaultTarget = old('reward_target', $store?->reward_target ?? 9);
    $defaultReward = old('reward_title', $store?->reward_title ?? 'Free regular coffee');
    $expandStore = !filled(old('name', $store?->name ?? '')) || $errors->has('name') || $errors->has('address');
@endphp
<x-onboarding-layout>
    <x-slot name="header">Set up your reward</x-slot>
    <x-onboarding-step-layout :step="1" :totalSteps="4" title="Set up your reward" subtitle="Choose what customers earn and how many stamps they need. Your café decides what earns a stamp.">
        <form method="POST" action="{{ route('merchant.onboarding.wizard.store-basics.store') }}" id="store-basics-form"
            x-data="{
                storeName: {{ json_encode(old('name', $store?->name ?? '')) }},
                rewardTarget: {{ json_encode($defaultTarget) }},
                rewardTitle: {{ json_encode($defaultReward) }},
                showStoreDetails: {{ $expandStore ? 'true' : 'false' }},
                customTarget: {{ in_array((string) $defaultTarget, ['6', '8', '9', '10'], true) ? 'false' : 'true' }},
                submitting: false
            }"
            @submit="if (submitting) { $event.preventDefault(); } else { submitting = true; document.getElementById('reward-continue').disabled = true; document.getElementById('reward-continue').textContent = 'Saving…'; }"
            @pageshow.window="submitting = false; document.getElementById('reward-continue').disabled = false; document.getElementById('reward-continue').textContent = 'Save and continue → Card design';">
            @csrf
            <x-form-error-summary form-id="store-basics-form" />
            <div class="space-y-6">
                <section class="rounded-xl border border-stone-200 bg-white p-5">
                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0"><h2 class="text-sm text-stone-500">Your store</h2><p class="mt-1 break-words font-semibold" x-text="storeName.trim() || 'Add your store name'"></p></div>
                        <button type="button" @click="showStoreDetails = !showStoreDetails" :aria-expanded="showStoreDetails" aria-controls="onboarding-store-details" class="min-h-11 px-3 font-semibold text-brand-700" x-text="showStoreDetails ? 'Close' : 'Edit'">Edit</button>
                    </div>
                    <div id="onboarding-store-details" x-show="showStoreDetails" class="mt-4 space-y-4 border-t border-stone-200 pt-4">
                        <div><label for="name" class="mb-2 block text-sm font-medium">Store name</label><input id="name" name="name" x-model="storeName" value="{{ old('name', $store?->name ?? '') }}" @invalid="showStoreDetails = true" maxlength="255" required class="{{ $inputClass }}"><x-input-error :messages="$errors->get('name')" class="mt-2" /></div>
                        <div><label for="address" class="mb-2 block text-sm font-medium">Address <span class="font-normal text-stone-500">(optional)</span></label><input id="address" name="address" value="{{ old('address', $store?->address ?? '') }}" maxlength="255" class="{{ $inputClass }}"><x-input-error :messages="$errors->get('address')" class="mt-2" /></div>
                    </div>
                </section>
                <x-onboarding-form-section title="1. What will customers earn?">
                    <label for="reward_title" class="mb-2 block text-sm font-medium">Reward</label>
                    <input id="reward_title" name="reward_title" type="text" x-model="rewardTitle" value="{{ $defaultReward }}" maxlength="255" autocomplete="off" placeholder="e.g. Free regular coffee" required aria-describedby="reward-help" class="{{ $inputClass }}">
                    <p id="reward-help" class="mt-2 text-sm text-stone-500">Be specific about the item or size, such as “Free regular coffee” or “Free pastry”.</p>
                    <x-input-error :messages="$errors->get('reward_title')" class="mt-2" />
                </x-onboarding-form-section>
                <x-onboarding-form-section title="2. How many stamps do they need?">
                    <div class="mb-4 flex flex-wrap gap-2" role="group" aria-label="Choose a stamp target">
                        @foreach([6, 8, 9, 10] as $target)
                            <button type="button" @click="rewardTarget = {{ $target }}; customTarget = false" :aria-pressed="!customTarget && Number(rewardTarget) === {{ $target }}" :class="!customTarget && Number(rewardTarget) === {{ $target }} ? 'bg-brand-700 text-white border-brand-700' : 'bg-white text-stone-700 border-stone-300'" class="min-h-11 min-w-11 rounded-xl border px-4 py-2 font-semibold">{{ $target }}</button>
                        @endforeach
                        <button type="button" @click="customTarget = true; $nextTick(() => $refs.stampTarget.focus())" :aria-pressed="customTarget" :class="customTarget ? 'bg-brand-700 text-white border-brand-700' : 'bg-white text-stone-700 border-stone-300'" class="min-h-11 rounded-xl border px-4 py-2 font-semibold">Custom</button>
                    </div>
                    <label for="reward_target" class="mb-2 block text-sm font-medium">Stamps needed</label>
                    <input id="reward_target" x-ref="stampTarget" name="reward_target" type="number" x-model.number="rewardTarget" @input="customTarget = true" value="{{ $defaultTarget }}" min="1" step="1" inputmode="numeric" required class="{{ $inputClass }}">
                    <p class="mt-2 text-sm text-stone-500">Choose a whole number of stamps. You decide which purchases or visits qualify.</p>
                    <x-input-error :messages="$errors->get('reward_target')" class="mt-2" />
                </x-onboarding-form-section>
                <section aria-labelledby="reward-summary" class="rounded-xl border border-brand-200 bg-brand-50 p-5">
                    <h2 id="reward-summary" class="text-sm font-semibold text-brand-700">Your reward at a glance</h2>
                    <p class="mt-2 text-lg font-semibold text-stone-900" aria-live="polite" x-text="Number.isInteger(Number(rewardTarget)) && Number(rewardTarget) > 0 && rewardTitle.trim() ? 'Collect ' + rewardTarget + (Number(rewardTarget) === 1 ? ' stamp' : ' stamps') + ' to earn: ' + rewardTitle.trim() + '.' : 'Enter a reward and a valid stamp count to see your summary.'"></p>
                    <p class="mt-2 text-sm text-stone-600">Next, add your logo and colours.</p>
                </section>
            </div>
        </form>
        <x-slot name="actions"><x-ui.button id="reward-continue" type="submit" form="store-basics-form" variant="primary" size="lg" class="w-full sm:w-auto min-h-11">Save and continue → Card design</x-ui.button></x-slot>
    </x-onboarding-step-layout>
</x-onboarding-layout>
