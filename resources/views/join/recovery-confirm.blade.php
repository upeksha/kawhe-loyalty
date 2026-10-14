<x-customer-layout :program="$program" :store="$store" title="Open my card">
    <x-slot name="brand"><x-customer-store-brand :store="$store ?? null" :program="$program ?? null" /></x-slot>
    <div class="customer-card p-6 sm:p-8">
        <h2 class="customer-card-title text-2xl font-bold mb-4">Open your card</h2>
        <p class="customer-card-body mb-6">Continue to your loyalty card at {{ $store->name }}. This private link can be used once.</p>
        <form method="POST" action="{{ route('customer.card.recover', ['token' => $token]) }}">
            @csrf
            <button class="customer-btn customer-btn-primary" type="submit">Open my card</button>
        </form>
    </div>
</x-customer-layout>
