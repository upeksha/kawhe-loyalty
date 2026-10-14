<x-merchant-design-layout title="Checkout Cancelled" subtitle="Return to billing to review your current plan.">

    <div class="w-full max-w-[720px]">
        <section class="space-y-4 rounded-2xl border border-stone-200 bg-white p-6">
            <h2 class="text-xl font-semibold text-stone-900 mb-2">Checkout Cancelled</h2>
            
            <p class="text-stone-600 mb-6">
                Your subscription checkout was cancelled. No charges were made.
            </p>

            @if(!empty($nextSteps))
                <div class="mb-6 rounded-lg border border-stone-200 bg-white p-4 text-left">
                    <p class="text-sm font-semibold text-stone-800">What this means</p>
                    <ul class="mt-2 space-y-2 text-sm leading-relaxed text-stone-600 list-disc list-inside">
                        @foreach($nextSteps as $step)
                            <li>{{ $step }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            
            <div class="space-y-3">
                <x-ui.button href="{{ route('billing.index') }}" variant="primary" class="w-full max-w-[400px]">
                    Back to Billing
                </x-ui.button>
            </div>
        </section>
    </div>
</x-merchant-design-layout>
