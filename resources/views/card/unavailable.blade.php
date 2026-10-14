<x-customer-layout :title="$title ?? 'Card unavailable'">
    <section class="customer-card p-6">
        <h1 class="customer-page-heading">{{ $title ?? 'Card unavailable' }}</h1>
        <p class="customer-page-description mb-6">{{ $message ?? 'This loyalty card is not available right now.' }}</p>
        <a href="/" class="customer-btn customer-btn-primary">Back to home</a>
    </section>
</x-customer-layout>
