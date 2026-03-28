@php
    $fields = [
        'first_name' => ['label' => 'First name', 'placeholder' => 'e.g. Jane'],
        'last_name'  => ['label' => 'Last name', 'placeholder' => 'e.g. Smith'],
        'phone'      => ['label' => 'Phone', 'placeholder' => 'e.g. 04 1234 5678'],
        'birthday'   => ['label' => 'Birthday', 'placeholder' => 'DD/MM/YYYY'],
    ];
    $initialConfig = array_merge(
        ['email' => ['enabled' => true, 'required' => true]],
        $config ?? []
    );
@endphp
<x-merchant-layout>
    <x-slot name="header">Customer form</x-slot>

    <x-onboarding-step-layout
        :step="3"
        :totalSteps="5"
        title="Choose what customer details to collect"
        subtitle="Keep sign-up quick and low-friction. Most merchants get better conversion when the first join is fast and the data request feels reasonable."
        :backUrl="route('merchant.onboarding.wizard.card-design')"
    >
        <form method="POST" action="{{ route('merchant.onboarding.wizard.customer-form.store') }}" id="customer-form-form"
            x-data="{
                config: {
                    email: { enabled: true, required: true },
                    first_name: { enabled: {{ ($config['first_name']['enabled'] ?? false) ? 'true' : 'false' }}, required: {{ ($config['first_name']['required'] ?? false) ? 'true' : 'false' }} },
                    last_name: { enabled: {{ ($config['last_name']['enabled'] ?? false) ? 'true' : 'false' }}, required: {{ ($config['last_name']['required'] ?? false) ? 'true' : 'false' }} },
                    phone: { enabled: {{ ($config['phone']['enabled'] ?? false) ? 'true' : 'false' }}, required: {{ ($config['phone']['required'] ?? false) ? 'true' : 'false' }} },
                    birthday: { enabled: {{ ($config['birthday']['enabled'] ?? false) ? 'true' : 'false' }}, required: {{ ($config['birthday']['required'] ?? false) ? 'true' : 'false' }} }
                },
                applyPreset(preset) {
                    const presets = {
                        fastest: {
                            first_name: { enabled: false, required: false },
                            last_name: { enabled: false, required: false },
                            phone: { enabled: false, required: false },
                            birthday: { enabled: false, required: false },
                        },
                        balanced: {
                            first_name: { enabled: true, required: false },
                            last_name: { enabled: false, required: false },
                            phone: { enabled: false, required: false },
                            birthday: { enabled: true, required: false },
                        },
                        marketing: {
                            first_name: { enabled: true, required: true },
                            last_name: { enabled: false, required: false },
                            phone: { enabled: true, required: false },
                            birthday: { enabled: true, required: false },
                        },
                    };
                    Object.entries(presets[preset]).forEach(([key, value]) => {
                        this.config[key] = { ...value };
                    });
                },
                get optionalFieldCount() {
                    return ['first_name', 'last_name', 'phone', 'birthday'].filter((key) => this.config[key].enabled).length;
                },
                get requiredFieldCount() {
                    return ['email', 'first_name', 'last_name', 'phone', 'birthday'].filter((key) => this.config[key].required).length;
                },
                get signupFrictionLabel() {
                    if (this.requiredFieldCount <= 1 && this.optionalFieldCount <= 1) return 'Fastest';
                    if (this.requiredFieldCount <= 2 && this.optionalFieldCount <= 2) return 'Balanced';
                    return 'Heavier';
                }
            }"
        >
            @csrf
            <x-form-error-summary form-id="customer-form-form" />
            <div class="grid grid-cols-1 sm:grid-cols-5 gap-6">
                <div class="sm:col-span-3 space-y-6">
                    <div class="rounded-xl border border-stone-200 bg-stone-50/80 p-5">
                        <p class="text-sm font-semibold text-stone-800">Quick presets</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="button" @click="applyPreset('fastest')" class="inline-flex items-center rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm font-medium text-stone-700 hover:bg-stone-50 transition-colors">Fastest signup</button>
                            <button type="button" @click="applyPreset('balanced')" class="inline-flex items-center rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm font-medium text-stone-700 hover:bg-stone-50 transition-colors">Balanced</button>
                            <button type="button" @click="applyPreset('marketing')" class="inline-flex items-center rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm font-medium text-stone-700 hover:bg-stone-50 transition-colors">Marketing-friendly</button>
                        </div>
                        <p class="mt-2 text-xs text-stone-500">These presets only change the form switches on this step. You can still fine-tune every field before saving.</p>
                    </div>
                    <div class="rounded-xl border border-amber-200/80 bg-amber-50/80 p-5">
                        <p class="text-sm font-semibold text-amber-900">Recommended for cafés</p>
                        <p class="text-sm text-amber-800/90 mt-1 leading-relaxed">Email (required), First name (optional), Birthday (optional). Shorter forms usually get more signups.</p>
                    </div>

                    {{-- Email: locked --}}
                    <div class="rounded-xl bg-stone-50 border border-stone-200 p-5 flex items-center justify-between gap-4">
                        <div>
                            <span class="font-semibold text-stone-800">Email</span>
                            <p class="text-sm text-stone-500 mt-0.5">Always collected and required</p>
                        </div>
                        <span class="text-xs font-semibold text-stone-500 bg-stone-200/80 px-2.5 py-1 rounded-lg">Required</span>
                    </div>

                    @foreach($fields as $key => $meta)
                        @php $cfg = $config[$key] ?? ['enabled' => false, 'required' => false]; @endphp
                        <div class="rounded-xl border transition-all duration-200 p-5"
                            :class="config.{{ $key }}.enabled ? 'bg-white border-stone-200 shadow-sm' : 'bg-stone-50/50 border-stone-200'"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <label class="flex items-center gap-3 cursor-pointer flex-1">
                                    <input type="hidden" :name="'{{ $key }}_enabled'" value="0">
                                    <input type="checkbox" name="{{ $key }}_enabled" value="1" x-model="config.{{ $key }}.enabled"
                                        class="w-5 h-5 rounded-lg border-stone-300 text-brand-600 focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-0">
                                    <span class="font-medium text-stone-800" :class="{ 'text-stone-500': !config.{{ $key }}.enabled }">{{ $meta['label'] }}</span>
                                </label>
                            </div>
                            <div x-show="config.{{ $key }}.enabled" class="mt-4 ml-8">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="hidden" :name="'{{ $key }}_required'" value="0">
                                    <input type="checkbox" name="{{ $key }}_required" value="1" x-model="config.{{ $key }}.required"
                                        class="w-4 h-4 rounded border-stone-300 text-brand-600 focus:ring-2 focus:ring-brand-500/20">
                                    <span class="text-sm text-stone-600">Required</span>
                                </label>
                            </div>
                        </div>
                    @endforeach

                    <p class="text-sm text-stone-500">Shorter forms usually get more signups.</p>
                </div>

                <div class="sm:col-span-2">
                    <details class="sm:hidden rounded-xl border border-stone-200 bg-stone-50/70 p-4">
                        <summary class="cursor-pointer list-none flex items-center justify-between text-sm font-semibold text-stone-800">
                            Signup form preview
                            <span class="text-xs font-medium text-stone-500">Tap to expand</span>
                        </summary>
                        <div class="mt-4 rounded-xl border border-stone-200 bg-white p-5 shadow-sm" role="status" aria-live="polite">
                            <p class="text-xs text-stone-400 mb-4">What customers will see when they join:</p>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-stone-700 mb-1">Email <span class="text-stone-400 font-normal">(required)</span></label>
                                    <input type="text" disabled placeholder="you@example.com" class="block w-full rounded-lg border border-stone-300 bg-stone-50 px-3 py-2 text-sm text-stone-500 placeholder-stone-400">
                                </div>
                                @foreach($fields as $key => $meta)
                                    <div x-show="config.{{ $key }}.enabled">
                                        <label class="block text-sm font-medium text-stone-700 mb-1">{{ $meta['label'] }} <span class="text-stone-400 font-normal" x-text="config.{{ $key }}.required ? '(required)' : '(optional)'"></span></label>
                                        <input type="text" disabled :placeholder="'{{ $meta['placeholder'] }}'" class="block w-full rounded-lg border border-stone-300 bg-stone-50 px-3 py-2 text-sm text-stone-500 placeholder-stone-400">
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </details>
                    <div class="hidden sm:block sticky top-8">
                        <div class="rounded-xl border border-stone-200 bg-stone-50/80 p-5 shadow-sm mb-4">
                            <p class="text-xs font-semibold text-stone-500 uppercase tracking-wider">Signup guidance</p>
                            <div class="mt-4 flex items-center justify-between rounded-xl border border-stone-200 bg-white px-4 py-3">
                                <span class="text-sm text-stone-700">Friction level</span>
                                <span class="font-semibold" :class="signupFrictionLabel === 'Fastest' ? 'text-emerald-700' : (signupFrictionLabel === 'Balanced' ? 'text-brand-700' : 'text-amber-700')" x-text="signupFrictionLabel"></span>
                            </div>
                            <p class="mt-3 text-xs leading-relaxed text-stone-500">
                                Keep the first join light if you want more signups. You can always collect more detail later at the counter or through follow-up campaigns.
                            </p>
                        </div>
                        <p class="text-xs font-semibold text-stone-500 uppercase tracking-wider mb-3">Signup form preview</p>
                        <div class="rounded-xl border border-stone-200 bg-white p-5 shadow-sm" role="status" aria-live="polite">
                            <p class="text-xs text-stone-400 mb-4">What customers will see when they join:</p>
                            <div class="space-y-4">
                                {{-- Email (always shown) --}}
                                <div>
                                    <label class="block text-sm font-medium text-stone-700 mb-1">Email <span class="text-stone-400 font-normal">(required)</span></label>
                                    <input type="text" disabled placeholder="you@example.com" class="block w-full rounded-lg border border-stone-300 bg-stone-50 px-3 py-2 text-sm text-stone-500 placeholder-stone-400">
                                </div>
                                @foreach($fields as $key => $meta)
                                    <div x-show="config.{{ $key }}.enabled">
                                        <label class="block text-sm font-medium text-stone-700 mb-1">{{ $meta['label'] }} <span class="text-stone-400 font-normal" x-text="config.{{ $key }}.required ? '(required)' : '(optional)'"></span></label>
                                        <input type="text" disabled :placeholder="'{{ $meta['placeholder'] }}'" class="block w-full rounded-lg border border-stone-300 bg-stone-50 px-3 py-2 text-sm text-stone-500 placeholder-stone-400">
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <x-slot name="actions">
            <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3">
                <form method="GET" action="{{ route('merchant.onboarding.wizard.card-design') }}" class="w-full sm:w-auto">
                    <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 text-sm font-medium text-stone-600 hover:text-stone-900 transition-colors focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 rounded-lg py-2 px-3">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        Back
                    </button>
                </form>
                <x-ui.button type="submit" form="customer-form-form" variant="primary" size="lg" class="w-full sm:w-auto rounded-xl min-w-[140px]">Continue</x-ui.button>
            </div>
        </x-slot>
    </x-onboarding-step-layout>
</x-merchant-layout>
