<?php

namespace App\Services\Wallet\Apple;

use App\Models\LoyaltyAccount;
use App\Services\Wallet\Apple\AppleWalletSerial;
use App\Services\Wallet\AppleWalletPassService;

/**
 * Wrapper service for Apple Wallet pass generation.
 * Provides a clean interface for the Apple Pass Web Service endpoints.
 */
class ApplePassService
{
    protected AppleWalletPassService $passGenerator;

    public function __construct(AppleWalletPassService $passGenerator)
    {
        $this->passGenerator = $passGenerator;
    }

    /**
     * Generate pkpass binary data for a loyalty account.
     *
     * @param LoyaltyAccount $account
     * @return string Raw pkpass binary data
     * @throws \Exception
     */
    public function generatePkpassForAccount(LoyaltyAccount $account): string
    {
        return $this->passGenerator->generatePass($account);
    }

    /**
     * Resolve loyalty account from serial number.
     * 
     * Serial number format: kawhe-{store_id}-{customer_id}
     * 
     * @param string $serialNumber
     * @return LoyaltyAccount|null
     */
    public function resolveLoyaltyAccount(string $serialNumber): ?LoyaltyAccount
    {
        // Use centralized serial helper
        $account = AppleWalletSerial::resolveAccount($serialNumber);
        if ($account) {
            return $account;
        }

        // Fallback: try public_token
        $account = \App\Models\LoyaltyAccount::where('public_token', $serialNumber)->first();
        if ($account) {
            return $account;
        }

        // Fallback: try numeric ID
        if (is_numeric($serialNumber)) {
            return \App\Models\LoyaltyAccount::find((int) $serialNumber);
        }

        return null;
    }

    /**
     * Get the serial number for a loyalty account.
     *
     * @param LoyaltyAccount $account
     * @return string
     */
    public function getSerialNumber(LoyaltyAccount $account): string
    {
        return AppleWalletSerial::fromAccount($account);
    }
}
