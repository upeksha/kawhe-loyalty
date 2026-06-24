<x-merchant-layout>
    @include('programs.partials.form', [
        'title' => 'Edit loyalty card',
        'subtitle' => 'Update reward settings, join form, and branding for this loyalty card.',
        'store' => $store,
        'program' => $program,
        'action' => route('merchant.stores.programs.update', [$store, $program]),
        'method' => 'PUT',
        'hasIssuedCards' => $hasIssuedCards,
    ])
</x-merchant-layout>
