@php($joining = $joining ?? false)
<x-customer-layout :program="$program" :store="$store" :title="$joining ? 'Check your email to continue' : 'Check your email'">
    <x-slot name="brand"><x-customer-store-brand :store="$store ?? null" :program="$program ?? null" /></x-slot>
    <div class="customer-card p-6 sm:p-8">
        <h2 class="customer-card-title text-2xl font-bold mb-4">{{ $joining ? 'Check your email to continue' : 'Check your email' }}</h2>
        @if($joining)
            <p class="customer-card-body mb-4">Follow the secure link in your email to join {{ $store->name }} or open your existing card there. Your cards, stamps and rewards at other cafés stay separate.</p>
            <p class="customer-card-body mb-4">For your security, we’ll use the email already on file, if one is available. Confirm your email before a new card is added. The link expires in 20 minutes.</p>
        @else
        <p class="customer-card-body mb-4">If we can match your details to a customer with an email address, we’ll send a private link to the email already on file. It expires in 20 minutes.</p>
        @endif
        <p class="customer-card-body mb-6">Check your spam folder too. No email, or no access to your old inbox? Ask the store for help. For your security, entering a phone number cannot change the email on a card.</p>
        <a class="customer-btn customer-btn-primary" href="{{ route($joining ? 'join.show' : 'join.existing', ['slug' => $program->slug, 't' => $token]) }}">Try again</a>
    </div>
</x-customer-layout>
