<x-customer-layout :account="$account" title="Confirm your email">
    <h1 class="customer-page-heading">Confirm your email</h1>
    <p class="customer-page-description mb-6">Confirm your email address for your loyalty card at {{ $account->store->name }}.</p>
    <section class="customer-card p-6 mb-6">
        <h2 class="text-lg font-semibold">Ready when you are</h2>
        <p class="customer-card-body mt-2 text-sm leading-relaxed">Press the button below to confirm your email address. Opening this page does not verify it.</p>
    </section>
    <form method="POST" action="{{ $action }}">
        @csrf
        <button type="submit" class="customer-btn customer-btn-primary">Verify my email</button>
    </form>
    <p class="customer-muted mt-6 text-sm">If you didn’t request this, you can close this page.</p>
</x-customer-layout>
