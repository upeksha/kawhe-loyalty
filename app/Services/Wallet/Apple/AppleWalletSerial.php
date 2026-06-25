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
     * Current format: kawhe-{loyalty_account_id}
     * Legacy format (still resolved): kawhe-{store_id}-{customer_id}
     */
    public static function fromAccount(LoyaltyAccount $account): string
    {
        return sprintf('kawhe-%d', $account->id);
    }

    /**
     * Parse serial number into structured parts.
     *
     * @return array{type: 'account', account_id: int}|array{type: 'legacy', store_id: int, customer_id: int}|null
     */
    public static function parse(string $serialNumber): ?array
    {
        if (preg_match('/^kawhe-(\d+)-(\d+)$/', $serialNumber, $matches)) {
            return [
                'type' => 'legacy',
                'store_id' => (int) $matches[1],
                'customer_id' => (int) $matches[2],
            ];
        }

        if (preg_match('/^kawhe-(\d+)$/', $serialNumber, $matches)) {
            return [
                'type' => 'account',
                'account_id' => (int) $matches[1],
            ];
        }

        return null;
    }

    /**
     * Resolve loyalty account from serial number.
     */
    public static function resolveAccount(string $serialNumber): ?LoyaltyAccount
    {
        $parsed = self::parse($serialNumber);

        if (! $parsed) {
            return null;
        }

        if ($parsed['type'] === 'account') {
            return LoyaltyAccount::find($parsed['account_id']);
        }

        $accounts = LoyaltyAccount::query()
            ->where('store_id', $parsed['store_id'])
            ->where('customer_id', $parsed['customer_id'])
            ->with('loyaltyProgram')
            ->orderBy('id')
            ->get();

        if ($accounts->isEmpty()) {
            return null;
        }

        if ($accounts->count() === 1) {
            return $accounts->first();
        }

        // Legacy serials pre-date multi-program support: prefer the default program card.
        $defaultAccount = $accounts->first(fn (LoyaltyAccount $account) => (bool) $account->loyaltyProgram?->is_default);

        return $defaultAccount ?? $accounts->first();
    }
}
