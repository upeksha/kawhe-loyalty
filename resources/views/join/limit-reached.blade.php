<x-customer-layout :program="$program" :store="$store" title="Limit Reached" centered>
    <div class="customer-card customer-card--status px-6 py-8 sm:px-8">
        <div class="text-center">
            <div class="mb-4">
                <svg class="mx-auto h-16 w-16 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>

            <h2 class="mb-2 text-2xl font-bold">Limit Reached</h2>

            <p class="customer-status-body mb-4">
                <strong class="text-inherit">{{ $store->name }}</strong> is not accepting new loyalty cards on the free plan right now.
            </p>

            <p class="customer-status-soft mb-6 text-sm">
                This card has reached the customer limit for the merchant’s current plan. Please ask staff to upgrade if you are a new customer. If you already joined, use “I already joined” or scan your existing card — those still work.
            </p>

            <a href="{{ route('join.index', ['slug' => $program->slug, 't' => $token]) }}" class="customer-btn customer-btn-status">
                Back to Join Page
            </a>
        </div>
    </div>
</x-customer-layout>
