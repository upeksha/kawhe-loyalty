<?php

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Controller;
use App\Models\AppleWalletRegistration;
use App\Models\LoyaltyAccount;
use App\Services\Wallet\Apple\ApplePassService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Byte5\PassGenerator;

class AppleWalletController extends Controller
{
    protected ApplePassService $passService;

    public function __construct(ApplePassService $passService)
    {
        $this->passService = $passService;
    }

    /**
     * Register device for pass updates.
     * 
     * POST /wallet/v1/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}/{serialNumber}
     */
    public function registerDevice(Request $request, string $deviceLibraryIdentifier, string $passTypeIdentifier, string $serialNumber): Response
    {
        $request->validate([
            'pushToken' => 'required|string',
        ]);

        // Resolve loyalty account from serial number
        $account = $this->passService->resolveLoyaltyAccount($serialNumber);
        if (!$account) {
            Log::warning('Apple Wallet registration: Account not found', [
                'serial_number' => $serialNumber,
                'device_library_identifier' => $deviceLibraryIdentifier,
            ]);
            return response('Pass not found', 404);
        }

        // Verify pass type identifier matches
        $expectedPassTypeIdentifier = config('passgenerator.pass_type_identifier');
        if ($passTypeIdentifier !== $expectedPassTypeIdentifier) {
            Log::warning('Apple Wallet registration: Pass type mismatch', [
                'expected' => $expectedPassTypeIdentifier,
                'received' => $passTypeIdentifier,
            ]);
            return response('Invalid pass type', 400);
        }

        // Upsert registration (idempotent)
        $registration = AppleWalletRegistration::updateOrCreate(
            [
                'device_library_identifier' => $deviceLibraryIdentifier,
                'pass_type_identifier' => $passTypeIdentifier,
                'serial_number' => $serialNumber,
            ],
            [
                'push_token' => $request->input('pushToken'),
                'loyalty_account_id' => $account->id,
                'active' => true,
                'last_registered_at' => now(),
            ]
        );

        $isNew = $registration->wasRecentlyCreated;

        Log::info('Apple Wallet device registered', [
            'registration_id' => $registration->id,
            'device_library_identifier' => $deviceLibraryIdentifier,
            'serial_number' => $serialNumber,
            'loyalty_account_id' => $account->id,
            'is_new' => $isNew,
        ]);

        return response('', $isNew ? 201 : 200);
    }

    /**
     * Unregister device from pass updates.
     * 
     * DELETE /wallet/v1/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}/{serialNumber}
     */
    public function unregisterDevice(string $deviceLibraryIdentifier, string $passTypeIdentifier, string $serialNumber): Response
    {
        $deleted = AppleWalletRegistration::where('device_library_identifier', $deviceLibraryIdentifier)
            ->where('pass_type_identifier', $passTypeIdentifier)
            ->where('serial_number', $serialNumber)
            ->update(['active' => false]);

        Log::info('Apple Wallet device unregistered', [
            'device_library_identifier' => $deviceLibraryIdentifier,
            'serial_number' => $serialNumber,
            'deleted' => $deleted > 0,
        ]);

        // Always return 200 (idempotent)
        return response('', 200);
    }

    /**
     * Get updated pass file.
     * 
     * GET /wallet/v1/passes/{passTypeIdentifier}/{serialNumber}
     */
    public function getPass(Request $request, string $passTypeIdentifier, string $serialNumber): Response
    {
        // Verify pass type identifier
        $expectedPassTypeIdentifier = config('passgenerator.pass_type_identifier');
        if ($passTypeIdentifier !== $expectedPassTypeIdentifier) {
            return response('Invalid pass type', 400);
        }

        // Resolve loyalty account
        $account = $this->passService->resolveLoyaltyAccount($serialNumber);
        if (!$account) {
            return response('Pass not found', 404);
        }

        $account->load(['store', 'customer']);

        // Check If-Modified-Since header for 304 Not Modified
        $ifModifiedSince = $request->header('If-Modified-Since');
        if ($ifModifiedSince) {
            $modifiedSince = strtotime($ifModifiedSince);
            $accountUpdated = $account->updated_at->timestamp;
            
            if ($modifiedSince && $accountUpdated <= $modifiedSince) {
                Log::debug('Apple Wallet pass: 304 Not Modified', [
                    'serial_number' => $serialNumber,
                    'if_modified_since' => $ifModifiedSince,
                    'account_updated_at' => $account->updated_at->toRfc7231String(),
                ]);
                return response('', 304)->header('Last-Modified', $account->updated_at->toRfc7231String());
            }
        }

        // Generate pass
        try {
            $pkpassData = $this->passService->generatePkpassForAccount($account);

            // Verify pass is valid
            if (substr($pkpassData, 0, 2) !== 'PK') {
                Log::error('Apple Wallet pass generation: Invalid ZIP', [
                    'serial_number' => $serialNumber,
                ]);
                return response('Pass generation failed', 500);
            }

            $mimeType = PassGenerator::getPassMimeType();

            Log::info('Apple Wallet pass generated for web service', [
                'serial_number' => $serialNumber,
                'loyalty_account_id' => $account->id,
                'size' => strlen($pkpassData),
            ]);

            return response($pkpassData, 200, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'attachment; filename="pass.pkpass"',
                'Content-Length' => strlen($pkpassData),
                'Cache-Control' => 'no-store',
                'Last-Modified' => $account->updated_at->toRfc7231String(),
            ]);
        } catch (\Exception $e) {
            Log::error('Apple Wallet pass generation failed', [
                'serial_number' => $serialNumber,
                'error' => $e->getMessage(),
            ]);
            return response('Pass generation failed', 500);
        }
    }

    /**
     * Get list of updated serial numbers for a device.
     * 
     * GET /wallet/v1/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}?passesUpdatedSince=<timestamp>
     */
    public function getUpdatedSerials(Request $request, string $deviceLibraryIdentifier, string $passTypeIdentifier): Response
    {
        $passesUpdatedSince = $request->query('passesUpdatedSince');

        // Get all active registrations for this device and pass type
        $registrations = AppleWalletRegistration::where('device_library_identifier', $deviceLibraryIdentifier)
            ->where('pass_type_identifier', $passTypeIdentifier)
            ->where('active', true)
            ->with('loyaltyAccount')
            ->get();

        $serialNumbers = [];

        if ($passesUpdatedSince) {
            // Filter by updated_at timestamp
            $updatedSince = is_numeric($passesUpdatedSince) 
                ? (int) $passesUpdatedSince 
                : strtotime($passesUpdatedSince);

            foreach ($registrations as $registration) {
                if ($registration->loyaltyAccount) {
                    $accountUpdated = $registration->loyaltyAccount->updated_at->timestamp;
                    if ($accountUpdated > $updatedSince) {
                        $serialNumbers[] = $registration->serial_number;
                    }
                }
            }
        } else {
            // Return all serial numbers if no timestamp provided
            $serialNumbers = $registrations->pluck('serial_number')->toArray();
        }

        return response()->json([
            'lastUpdated' => now()->timestamp,
            'serialNumbers' => $serialNumbers,
        ]);
    }

    /**
     * Log endpoint for Apple Wallet.
     * 
     * POST /wallet/v1/log
     */
    public function log(Request $request): Response
    {
        $logs = $request->input('logs', []);

        foreach ($logs as $log) {
            Log::info('Apple Wallet log', [
                'level' => $log['level'] ?? 'info',
                'message' => $log['message'] ?? '',
                'data' => $log,
            ]);
        }

        return response('', 200);
    }
}
