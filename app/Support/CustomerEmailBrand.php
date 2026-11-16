<?php

namespace App\Support;

use App\Models\Store;
use Illuminate\Mail\Mailables\Address;

class CustomerEmailBrand
{
    public static function from(?Store $store, ?string $name = null): ?Address
    {
        $displayName = trim(preg_replace('/[\x00-\x1f\x7f]+/', ' ', $store?->name ?? $name ?? ''));
        $address = config('mail.from.address');

        return $displayName !== '' && $address ? new Address($address, $displayName) : null;
    }

    public static function replyTo(?Store $store): array
    {
        return $store?->customer_reply_to ? [new Address($store->customer_reply_to)] : [];
    }
}
