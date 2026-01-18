<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyAccount;
use App\Services\Wallet\AppleWalletPassService;
use App\Services\Wallet\GoogleWalletPassService;
use Byte5\PassGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WalletController extends Controller
{
    protected $applePassService;
    protected $googlePassService;

    public function __construct(AppleWalletPassService $applePassService, GoogleWalletPassService $googlePassService)
    {
        $this->applePassService = $applePassService;
        $this->googlePassService = $googlePassService;
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
            $pkpassData = $this->applePassService->generatePass($account);
            $storeSlug = $account->store->slug ?? 'kawhe';
            
            // Verify the pass is valid (is a ZIP)
            if (substr($pkpassData, 0, 2) !== 'PK') {
                \Log::error('Generated pass is not a valid ZIP file', [
                    'public_token' => $public_token,
                    'first_bytes' => bin2hex(substr($pkpassData, 0, 10)),
                ]);
                abort(500, 'Invalid pass file generated');
            }
            
            // Log pass generation for debugging
            \Log::info('Apple Wallet pass generated', [
                'public_token' => $public_token,
                'size' => strlen($pkpassData),
                'store_slug' => $storeSlug,
            ]);

            // Use the package's MIME type method and add all required headers for Safari
            // Safari on iPhone needs specific headers to properly handle .pkpass files
            $mimeType = PassGenerator::getPassMimeType();
            
            // Create response with binary content
            $response = response($pkpassData, 200, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => sprintf('attachment; filename="kawhe-%s.pkpass"', $storeSlug),
                'Content-Length' => strlen($pkpassData),
                'Content-Transfer-Encoding' => 'binary',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'Accept-Ranges' => 'bytes',
            ]);
            
            return $response;
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

    /**
     * Generate "Save to Google Wallet" link for a loyalty account
     *
     * @param string $public_token
     * @return \Illuminate\Http\RedirectResponse
     */
    public function saveGooglePass(string $public_token)
    {
        $account = LoyaltyAccount::with(['store', 'customer'])
            ->where('public_token', $public_token)
            ->firstOrFail();

        try {
            $saveUrl = $this->googlePassService->generateSaveLink($account);
            
            \Log::info('Google Wallet save link generated', [
                'public_token' => $public_token,
                'account_id' => $account->id,
            ]);

            // Redirect to Google Wallet save URL
            return redirect($saveUrl);
        } catch (\Exception $e) {
            \Log::error('Failed to generate Google Wallet save link', [
                'public_token' => $public_token,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // In development, show more details
            if (config('app.debug')) {
                return response()->json([
                    'error' => 'Failed to generate Google Wallet save link',
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }

            abort(500, 'Failed to generate Google Wallet save link. Please try again later.');
        }
    }
}
