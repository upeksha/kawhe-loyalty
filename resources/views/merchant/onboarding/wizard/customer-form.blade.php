@php
    use App\Support\RegistrationFormConfig;

    $initialConfig = RegistrationFormConfig::normalize($config ?? []);
@endphp
<x-onboarding-layout>
    <x-slot name="header">Customer form</x-slot>

    <x-onboarding-step-layout
        :step="3"
        :totalSteps="4"
        title="Choose what customer details to collect"
        subtitle="Keep sign-up quick and low-friction. Most merchants get better conversion when the first join is fast and the data request feels reasonable."
        :backUrl="route('merchant.onboarding.wizard.card-design')"
    >
        <form method="POST" action="{{ route('merchant.onboarding.wizard.customer-form.store') }}" id="customer-form-form"
            x-data="registrationFormConfigState(@js($initialConfig))"
        >
            @csrf
            <x-form-error-summary form-id="customer-form-form" />
            <x-registration-form-config-editor
                :config="$initialConfig"
                show-presets
                show-cafe-tip
                show-preview
                show-friction-guide
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
</x-onboarding-layout>
