@php
    use App\Support\RegistrationFormConfig;

    $initialConfig = RegistrationFormConfig::normalize($config ?? []);
@endphp
<x-onboarding-layout>
    <x-slot name="header">Customer form</x-slot>

    <div x-data="registrationFormConfigState(@js($initialConfig))">
    <x-onboarding-step-layout
        :step="3"
        :store="$store"
        title="Choose what customers share"
        subtitle="Keep joining quick. Email is always required."
    >
        <x-slot:preview>
            <div class="space-y-4 text-center">
                <h2 class="text-2xl font-bold">Customer signup preview</h2>
                <x-onboarding-phone>
                    <div class="space-y-4 rounded-xl bg-white p-4 text-left text-stone-900">
                        <p class="text-lg font-bold text-brand-900">Join {{ $store->name }}</p>
                        <div>
                            <p class="text-sm font-semibold">Email <span class="font-normal text-stone-600">(required)</span></p>
                            <p class="mt-1 rounded-lg border border-stone-200 bg-stone-50 px-3 py-2 text-sm text-stone-500">you@example.com</p>
                        </div>
                        @foreach(\App\Support\RegistrationFormConfig::fieldDefinitions() as $key => $meta)
                            <div x-show="config.{{ $key }}.enabled" x-cloak>
                                <p class="text-sm font-semibold">{{ $meta['label'] }} <span class="font-normal text-stone-600" x-text="config.{{ $key }}.required ? '(required)' : '(optional)'"></span></p>
                                <p class="mt-1 rounded-lg border border-stone-200 bg-stone-50 px-3 py-2 text-sm text-stone-500">{{ $meta['placeholder'] }}</p>
                            </div>
                        @endforeach
                        <p class="rounded-lg bg-brand-800 px-3 py-2 text-center text-sm text-white">Get my loyalty card</p>
                    </div>
                </x-onboarding-phone>
                <p class="text-sm">The preview updates as you select fields.</p>
            </div>
        </x-slot:preview>
        <form method="POST" action="{{ route('merchant.onboarding.wizard.customer-form.store') }}" id="customer-form-form">
            @csrf
            <x-form-error-summary form-id="customer-form-form" />
            <x-registration-form-config-editor
                :config="$initialConfig"
                show-presets
                show-cafe-tip
            />
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
    </div>
</x-onboarding-layout>
