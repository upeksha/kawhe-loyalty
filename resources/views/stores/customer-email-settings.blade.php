<section id="customer-emails" class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="customer-emails-title">
    <h2 id="customer-emails-title" class="text-lg font-semibold text-stone-900">Customer emails</h2>
    <p class="mt-1 text-sm text-stone-600">Your loyalty emails use your store name and logo. Add a welcome note and choose where customer replies go.</p>
    @if(session('customer_email_status'))
        <p class="mt-3 text-sm text-brand-700" role="status">{{ session('customer_email_status') }}</p>
    @endif
    <form method="POST" action="{{ route('merchant.stores.customer-email.update', $store) }}" class="mt-5 space-y-5">
        @csrf
        @method('PUT')
        <div>
            <label for="customer-welcome-note" class="mb-1.5 block text-sm font-medium text-stone-800">Welcome note</label>
            <textarea id="customer-welcome-note" name="welcome_note" maxlength="400" rows="3" class="w-full rounded-xl border-stone-300 text-sm" aria-describedby="customer-welcome-help">{{ old('welcome_note', $store->customer_welcome_note) }}</textarea>
            <p id="customer-welcome-help" class="mt-1 text-sm text-stone-600">Optional. Up to 400 characters. It appears in the email customers get when they join.</p>
            @error('welcome_note', 'customerEmail')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="customer-reply-to" class="mb-1.5 block text-sm font-medium text-stone-800">Public reply-to email</label>
            <x-ui.input id="customer-reply-to" name="reply_to" type="email" maxlength="254" value="{{ old('reply_to', $store->pending_customer_reply_to ?? $store->customer_reply_to) }}" aria-describedby="customer-reply-help" />
            <p id="customer-reply-help" class="mt-1 text-sm text-stone-600">Optional. Customers can see this address when they reply. Kawhe will ask you to confirm it before use.</p>
            @if($store->pending_customer_reply_to)
                <p class="mt-1 text-sm text-amber-800">Waiting for confirmation from {{ $store->pending_customer_reply_to }}. @if($store->customer_reply_to) Replies still go to {{ $store->customer_reply_to }}. @endif</p>
            @elseif($store->customer_reply_to)
                <p class="mt-1 text-sm text-brand-700">Confirmed. Customer replies go to this address.</p>
            @endif
            @error('reply_to', 'customerEmail')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.button type="submit">Save email settings</x-ui.button>
            <x-ui.button :href="route('merchant.stores.customer-email.preview', $store)" target="_blank" rel="noopener" variant="secondary">Preview saved welcome email ↗</x-ui.button>
        </div>
    </form>
</section>
