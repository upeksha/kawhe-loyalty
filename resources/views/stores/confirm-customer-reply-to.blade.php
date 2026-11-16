<x-guest-layout>
    @isset($store)
        <h1 class="text-xl font-semibold">Confirm where customer replies go</h1>
        <p class="mt-3 text-sm text-stone-600">Replies to {{ $store->name }} loyalty emails will go to {{ $store->pending_customer_reply_to }}.</p>
        <form action="{{ $confirmUrl }}" method="POST" class="mt-5">
            @csrf
            <x-ui.button type="submit">Confirm reply address</x-ui.button>
        </form>
    @else
        <h1 class="text-xl font-semibold">Reply address confirmed</h1>
        <p class="mt-3 text-sm text-stone-600">Replies to the café’s customer loyalty emails will now go to this inbox.</p>
    @endisset
</x-guest-layout>
