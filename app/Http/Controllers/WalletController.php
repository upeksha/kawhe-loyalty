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
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'config' => [
                    'certificate_store_path' => config('passgenerator.certificate_store_path'),
                    'certificate_store_password' => config('passgenerator.certificate_store_password') ? '***SET***' : 'NOT SET',
                    'wwdr_certificate_path' => config('passgenerator.wwdr_certificate_path'),
                    'pass_type_identifier' => config('passgenerator.pass_type_identifier'),
                    'team_identifier' => config('passgenerator.team_identifier'),
                    'organization_name' => config('passgenerator.organization_name'),
                    'storage_disk' => config('passgenerator.storage_disk'),
                    'storage_path' => config('passgenerator.storage_path'),
                ],
                'file_checks' => [
                    'certificate_exists' => file_exists(storage_path('app/private/' . config('passgenerator.certificate_store_path'))),
                    'wwdr_exists' => file_exists(storage_path('app/private/' . config('passgenerator.wwdr_certificate_path'))),
                    'passes_dir_exists' => is_dir(storage_path('app/private/' . config('passgenerator.storage_path'))),
                    'passes_dir_writable' => is_writable(storage_path('app/private/' . config('passgenerator.storage_path'))),
                ],
            ]);

            // In development, show more details
            if (config('app.debug')) {
                return response()->json([
                    'error' => 'Failed to generate Apple Wallet pass',
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }

            abort(500, 'Failed to generate Apple Wallet pass. Please try again later.');
        }
    }
}
