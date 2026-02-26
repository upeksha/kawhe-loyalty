<x-merchant-layout>
    <x-slot name="header">You're all set</x-slot>

    <x-onboarding-step-layout
        :step="5"
        :totalSteps="5"
        title="Launch your digital stamp card"
        subtitle="You can begin using Kawhe right away — your first 50 customer cards are included."
        :backUrl="route('merchant.onboarding.wizard.card-ready')"
    >
        <form method="POST" action="{{ route('merchant.onboarding.wizard.complete') }}" id="continue-trial-form">
            @csrf
            <div class="rounded-xl border border-brand-200 bg-brand-50/80 p-6">
                <p class="text-stone-800 leading-relaxed">
                    Start free with <strong>50 customer cards</strong>. Share your QR code or join link with customers—no app download required.
                </p>
                <p class="text-sm text-stone-600 mt-3 leading-relaxed">
                    You can always add more stores, change branding, or upgrade your plan from the dashboard.
                </p>
            </div>
        </form>

        <x-slot name="actions">
            <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3">
                <form method="GET" action="{{ route('merchant.onboarding.wizard.card-ready') }}" class="w-full sm:w-auto">
                    <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 text-sm font-medium text-stone-600 hover:text-stone-900 transition-colors focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 rounded-lg py-2 px-3">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        Back
                    </button>
                </form>
                <x-ui.button type="submit" form="continue-trial-form" variant="primary" size="lg" class="w-full sm:w-auto rounded-xl min-w-[160px]">Get started</x-ui.button>
            </div>
        </x-slot>
    </x-onboarding-step-layout>
</x-merchant-layout>
