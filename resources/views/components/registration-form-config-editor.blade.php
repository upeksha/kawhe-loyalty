@props([
    'config' => [],
    'showPresets' => true,
    'showCafeTip' => false,
    'showPreview' => false,
    'showFrictionGuide' => false,
])

@php
    use App\Support\RegistrationFormConfig;

    $fields = RegistrationFormConfig::fieldDefinitions();
    $initialConfig = RegistrationFormConfig::normalize($config);
@endphp

@once
    <script>
        function registrationFormConfigState(initial) {
            const base = {
                email: { enabled: true, required: true },
                first_name: { enabled: false, required: false },
                last_name: { enabled: false, required: false },
                phone: { enabled: false, required: false },
                birthday: { enabled: false, required: false },
            };

            const config = { ...base, ...initial };
            config.email = { enabled: true, required: true };

            return {
                config,
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

                    Object.entries(presets[preset] || {}).forEach(([key, value]) => {
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
                },
            };
        }
    </script>
@endonce

<div {{ $attributes->merge(['class' => $showPreview ? 'grid grid-cols-1 sm:grid-cols-5 gap-6' : 'space-y-6']) }}>
    <div @class(['space-y-6', 'sm:col-span-3' => $showPreview])>
        @if($showPresets)
            <div class="rounded-xl border border-stone-200 bg-stone-50/80 p-5">
                <p class="text-sm font-semibold text-stone-800">Quick presets</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" @click="applyPreset('fastest')" class="inline-flex items-center rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm font-medium text-stone-700 hover:bg-stone-50 transition-colors">Fastest signup</button>
                    <button type="button" @click="applyPreset('balanced')" class="inline-flex items-center rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm font-medium text-stone-700 hover:bg-stone-50 transition-colors">Balanced</button>
                    <button type="button" @click="applyPreset('marketing')" class="inline-flex items-center rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm font-medium text-stone-700 hover:bg-stone-50 transition-colors">Marketing-friendly</button>
                </div>
                <p class="mt-2 text-xs text-stone-500">These presets only change the form switches below. You can still fine-tune every field before saving.</p>
            </div>
        @endif

        @if($showCafeTip)
            <div class="rounded-xl border border-amber-200/80 bg-amber-50/80 p-5">
                <p class="text-sm font-semibold text-amber-900">Recommended for cafés</p>
                <p class="text-sm text-amber-800/90 mt-1 leading-relaxed">Email (required), First name (optional), Birthday (optional). Shorter forms usually get more signups.</p>
            </div>
        @endif

        <div class="rounded-xl bg-stone-50 border border-stone-200 p-5 flex items-center justify-between gap-4">
            <div>
                <span class="font-semibold text-stone-800">Email</span>
                <p class="text-sm text-stone-500 mt-0.5">Always collected and required</p>
            </div>
            <span class="text-xs font-semibold text-stone-500 bg-stone-200/80 px-2.5 py-1 rounded-lg">Required</span>
        </div>

        @foreach($fields as $key => $meta)
            <div class="rounded-xl border transition-all duration-200 p-5"
                x-bind:class="config.{{ $key }}.enabled ? 'bg-white border-stone-200 shadow-sm' : 'bg-stone-50/50 border-stone-200'"
            >
                <div class="flex items-start justify-between gap-4">
                    <label class="flex items-center gap-3 cursor-pointer flex-1">
                        <input type="hidden" :name="'{{ $key }}_enabled'" value="0">
                        <input type="checkbox" name="{{ $key }}_enabled" value="1" x-model="config.{{ $key }}.enabled"
                            class="w-5 h-5 rounded-lg border-stone-300 text-brand-600 focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-0">
                        <span class="font-medium text-stone-800" x-bind:class="{ 'text-stone-500': !config.{{ $key }}.enabled }">{{ $meta['label'] }}</span>
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

    @if($showPreview)
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
                @if($showFrictionGuide)
                    <div class="rounded-xl border border-stone-200 bg-stone-50/80 p-5 shadow-sm mb-4">
                        <p class="text-xs font-semibold text-stone-500 uppercase tracking-wider">Signup guidance</p>
                        <div class="mt-4 flex items-center justify-between rounded-xl border border-stone-200 bg-white px-4 py-3">
                            <span class="text-sm text-stone-700">Friction level</span>
                            <span class="font-semibold" x-bind:class="signupFrictionLabel === 'Fastest' ? 'text-emerald-700' : (signupFrictionLabel === 'Balanced' ? 'text-brand-700' : 'text-amber-700')" x-text="signupFrictionLabel"></span>
                        </div>
                        <p class="mt-3 text-xs leading-relaxed text-stone-500">
                            Keep the first join light if you want more signups. You can always collect more detail later at the counter or through follow-up campaigns.
                        </p>
                    </div>
                @endif
                <p class="text-xs font-semibold text-stone-500 uppercase tracking-wider mb-3">Signup form preview</p>
                <div class="rounded-xl border border-stone-200 bg-white p-5 shadow-sm" role="status" aria-live="polite">
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
            </div>
        </div>
    @endif
</div>
