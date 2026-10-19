<x-customer-layout :program="$program" :store="$store" :title="'Continue to '.$store->name">
    <x-slot name="brand"><x-customer-store-brand :store="$store ?? null" :program="$program ?? null" /></x-slot>
    <div class="customer-card p-6 sm:p-8">
        <h2 class="customer-card-title text-2xl font-bold mb-4">Continue to {{ $store->name }}</h2>
        <p class="customer-card-body mb-6">Confirm below to join {{ $store->name }} or open your existing card there. Your cards and rewards at other cafés stay separate. This private link can be used once.</p>
        <form method="POST" action="{{ route('customer.card.recover', ['token' => $token]) }}">
            @csrf
            <button class="customer-btn customer-btn-primary" type="submit">Confirm and continue</button>
        </form>
    </div>
</x-customer-layout>
