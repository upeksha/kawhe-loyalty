<?php

namespace App\Support;

use App\Models\Store;
use Illuminate\Mail\Mailables\Address;

class CustomerEmailBrand
{
    public static function logoUrl(?Store $store): ?string
    {
        $card = $store?->defaultLoyaltyProgram ?? $store?->defaultProgram;

        return $card?->logo_url ?? $store?->logo_url;
    }

    /** @return array{background: string, button: string, buttonText: string, link: string} */
    public static function colors(?Store $store): array
    {
        if (! $store) {
            return ['background' => '#f0f7f4', 'button' => '#2d5d47', 'buttonText' => '#ffffff', 'link' => '#2d5d47'];
        }

        $card = $store->defaultLoyaltyProgram ?? $store->defaultProgram;
        $brand = $card ?? $store;
        $background = $brand->card_design === 'illustrated_coffee'
            ? CardDesign::colors($brand)[0]
            : ($brand->background_color ?? $store->background_color);

        return ProgramBranding::emailColors(
            $brand->card_design === 'classic' ? $brand->brand_color : null,
            $background,
        );
    }

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
