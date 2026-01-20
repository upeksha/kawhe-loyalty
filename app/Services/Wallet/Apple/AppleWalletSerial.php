<?php

namespace App\Services\Wallet\Apple;

use App\Models\LoyaltyAccount;

/**
 * Centralized helper for Apple Wallet serial numbers.
 * 
 * Ensures consistent serial number format across:
 * - Pass generation
 * - Registration storage
 * - Push notifications
 * - Wallet sync
 */
class AppleWalletSerial
{
    /**
     * Generate serial number from loyalty account.
     * 
     * Format: kawhe-{store_id}-{customer_id}
     * 
     * @param LoyaltyAccount $account
     * @return string
     */
    public static function fromAccount(LoyaltyAccount $account): string
    {
        return sprintf('kawhe-%d-%d', $account->store_id, $account->customer_id);
    }

    /**
     * Parse serial number to extract store_id and customer_id.
     * 
     * @param string $serialNumber
     * @return array{store_id: int, customer_id: int}|null
     */
    public static function parse(string $serialNumber): ?array
    {
        if (preg_match('/^kawhe-(\d+)-(\d+)$/', $serialNumber, $matches)) {
            return [
                'store_id' => (int) $matches[1],
                'customer_id' => (int) $matches[2],
            ];
        }
        return null;
    }

    /**
     * Resolve loyalty account from serial number.
     * 
     * @param string $serialNumber
     * @return LoyaltyAccount|null
     */
    public static function resolveAccount(string $serialNumber): ?LoyaltyAccount
    {
        $parsed = self::parse($serialNumber);
        if ($parsed) {
            return LoyaltyAccount::where('store_id', $parsed['store_id'])
                ->where('customer_id', $parsed['customer_id'])
                ->first();
        }
        return null;
    }
}
