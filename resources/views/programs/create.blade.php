<x-merchant-layout>
    @include('programs.partials.form', [
        'title' => 'Create loyalty card',
        'subtitle' => 'Add an additional loyalty card for this store. Each card gets its own reward settings, QR code, and join flow.',
        'store' => $store,
        'program' => null,
        'action' => route('merchant.stores.programs.store', $store),
        'method' => 'POST',
        'hasIssuedCards' => false,
        'usageStats' => $usageStats ?? null,
    ])
</x-merchant-layout>
