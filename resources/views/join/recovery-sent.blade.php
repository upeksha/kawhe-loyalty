<x-customer-layout :program="$program" :store="$store" title="Check your email">
    <x-slot name="brand"><x-customer-store-brand :store="$store ?? null" :program="$program ?? null" /></x-slot>
    <div class="customer-card p-6 sm:p-8">
        <h2 class="customer-card-title text-2xl font-bold mb-4">Check your email</h2>
        <p class="customer-card-body mb-4">If we can match your details to a customer with an email address, we’ll send a private link to the email already on file. It expires in 20 minutes.</p>
        <p class="customer-card-body mb-6">Check your spam folder too. No email, or no access to your old inbox? Ask the store for help. For your security, entering a phone number cannot change the email on a card.</p>
        <a class="customer-btn customer-btn-primary" href="{{ route('join.existing', ['slug' => $program->slug, 't' => $token]) }}">Try again</a>
    </div>
</x-customer-layout>
