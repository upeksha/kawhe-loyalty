<x-merchant-layout>
    <x-slot name="header">
        {{ __('Subscription Cancelled') }}
    </x-slot>

    <div class="max-w-2xl mx-auto">
        <x-ui.card class="p-6 text-center">
            <h2 class="text-2xl font-bold text-stone-900 mb-2">Checkout Cancelled</h2>
            
            <p class="text-stone-600 mb-6">
                Your subscription checkout was cancelled. No charges were made.
            </p>
            
            <div class="space-y-3">
                <x-ui.button href="{{ route('billing.index') }}" variant="primary">
                    Back to Billing
                </x-ui.button>
            </div>
        </x-ui.card>
    </div>
</x-merchant-layout>
