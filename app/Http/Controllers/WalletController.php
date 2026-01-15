<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyAccount;
use App\Services\Wallet\AppleWalletPassService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WalletController extends Controller
{
    protected $passService;

    public function __construct(AppleWalletPassService $passService)
    {
        $this->passService = $passService;
    }

    /**
     * Download Apple Wallet pass for a loyalty account
     *
     * @param string $public_token
     * @return Response
     */
    public function downloadApplePass(string $public_token)
    {
        $account = LoyaltyAccount::with(['store', 'customer'])
            ->where('public_token', $public_token)
            ->firstOrFail();

        try {
            $pkpassData = $this->passService->generatePass($account);
            $storeSlug = $account->store->slug ?? 'kawhe';

            return response($pkpassData, 200, [
                'Content-Type' => 'application/vnd.apple.pkpass',
                'Content-Disposition' => sprintf('attachment; filename="kawhe-%s.pkpass"', $storeSlug),
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to generate Apple Wallet pass', [
                'public_token' => $public_token,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            abort(500, 'Failed to generate Apple Wallet pass. Please try again later.');
        }
    }
}
