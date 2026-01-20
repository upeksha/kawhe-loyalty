<?php

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Controller;
use App\Models\AppleWalletRegistration;
use App\Models\LoyaltyAccount;
use App\Services\Wallet\Apple\ApplePassService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
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
        // Log incoming request for debugging
        Log::info('Apple Wallet registration request received', [
            'device_library_identifier' => $deviceLibraryIdentifier,
            'pass_type_identifier' => $passTypeIdentifier,
            'serial_number' => $serialNumber,
            'has_push_token' => $request->has('pushToken'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $request->validate([
            'pushToken' => 'required|string',
        ]);

        $pushToken = $request->input('pushToken');

        // Resolve loyalty account from serial number
        $account = $this->passService->resolveLoyaltyAccount($serialNumber);
        if (!$account) {
            Log::warning('Apple Wallet registration: Account not found', [
                'serial_number' => $serialNumber,
                'device_library_identifier' => $deviceLibraryIdentifier,
                'pass_type_identifier' => $passTypeIdentifier,
            ]);
            return response('Pass not found', 404);
        }

        // Verify pass type identifier matches
        $expectedPassTypeIdentifier = config('passgenerator.pass_type_identifier');
        if ($passTypeIdentifier !== $expectedPassTypeIdentifier) {
            Log::warning('Apple Wallet registration: Pass type mismatch', [
                'expected' => $expectedPassTypeIdentifier,
                'received' => $passTypeIdentifier,
                'serial_number' => $serialNumber,
                'device_library_identifier' => $deviceLibraryIdentifier,
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
                'push_token' => $pushToken,
                'loyalty_account_id' => $account->id,
                'active' => true,
                'last_registered_at' => now(),
            ]
        );

        $isNew = $registration->wasRecentlyCreated;

        Log::info('Apple Wallet device registered successfully', [
            'registration_id' => $registration->id,
            'device_library_identifier' => $deviceLibraryIdentifier,
            'serial_number' => $serialNumber,
            'pass_type_identifier' => $passTypeIdentifier,
            'loyalty_account_id' => $account->id,
            'public_token' => $account->public_token,
            'push_token_length' => strlen($pushToken),
            'is_new' => $isNew,
            'ip_address' => $request->ip(),
        ]);

        return response('', $isNew ? 201 : 200);
    }

    /**
     * Unregister device from pass updates.
     * 
     * DELETE /wallet/v1/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}/{serialNumber}
     */
    public function unregisterDevice(Request $request, string $deviceLibraryIdentifier, string $passTypeIdentifier, string $serialNumber): Response
    {
        Log::info('Apple Wallet unregistration request', [
            'device_library_identifier' => $deviceLibraryIdentifier,
            'pass_type_identifier' => $passTypeIdentifier,
            'serial_number' => $serialNumber,
            'ip_address' => $request->ip(),
        ]);

        $deleted = AppleWalletRegistration::where('device_library_identifier', $deviceLibraryIdentifier)
            ->where('pass_type_identifier', $passTypeIdentifier)
            ->where('serial_number', $serialNumber)
            ->update(['active' => false]);

        Log::info('Apple Wallet device unregistered', [
            'device_library_identifier' => $deviceLibraryIdentifier,
            'serial_number' => $serialNumber,
            'pass_type_identifier' => $passTypeIdentifier,
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
        Log::info('Apple Wallet pass retrieval request', [
            'pass_type_identifier' => $passTypeIdentifier,
            'serial_number' => $serialNumber,
            'if_modified_since' => $request->header('If-Modified-Since'),
            'ip_address' => $request->ip(),
        ]);

        // Verify pass type identifier
        $expectedPassTypeIdentifier = config('passgenerator.pass_type_identifier');
        if ($passTypeIdentifier !== $expectedPassTypeIdentifier) {
            Log::warning('Apple Wallet pass retrieval: Invalid pass type', [
                'expected' => $expectedPassTypeIdentifier,
                'received' => $passTypeIdentifier,
                'serial_number' => $serialNumber,
            ]);
            return response('Invalid pass type', 400);
        }

        // Resolve loyalty account
        $account = $this->passService->resolveLoyaltyAccount($serialNumber);
        if (!$account) {
            Log::warning('Apple Wallet pass retrieval: Account not found', [
                'serial_number' => $serialNumber,
            ]);
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
    public function getUpdatedSerials(Request $request, string $deviceLibraryIdentifier, string $passTypeIdentifier): JsonResponse
    {
        Log::info('Apple Wallet device updates list requested', [
            'device_library_identifier' => $deviceLibraryIdentifier,
            'pass_type_identifier' => $passTypeIdentifier,
            'passes_updated_since' => $request->query('passesUpdatedSince'),
            'ip_address' => $request->ip(),
        ]);

        $passesUpdatedSince = $request->query('passesUpdatedSince');

        // Get all active registrations for this device and pass type
        $registrations = AppleWalletRegistration::where('device_library_identifier', $deviceLibraryIdentifier)
            ->where('pass_type_identifier', $passTypeIdentifier)
            ->where('active', true)
            ->with('loyaltyAccount')
            ->get();

        $serialNumbers = [];
        $latestUpdated = 0;

        if ($passesUpdatedSince) {
            // Filter by updated_at timestamp
            $updatedSince = is_numeric($passesUpdatedSince) 
                ? (int) $passesUpdatedSince 
                : strtotime($passesUpdatedSince);

            if ($updatedSince === false) {
                // Invalid timestamp, treat as 0
                $updatedSince = 0;
            }

            foreach ($registrations as $registration) {
                if ($registration->loyaltyAccount && $registration->loyaltyAccount->updated_at) {
                    $accountUpdated = $registration->loyaltyAccount->updated_at->timestamp;
                    if ($accountUpdated > $updatedSince) {
                        $serialNumbers[] = $registration->serial_number;
                        // Track the latest updated timestamp
                        if ($accountUpdated > $latestUpdated) {
                            $latestUpdated = $accountUpdated;
                        }
                    }
                } else {
                    // If no account or updated_at, include it (safer default)
                    $serialNumbers[] = $registration->serial_number;
                }
            }
        } else {
            // Return all serial numbers if no timestamp provided
            $serialNumbers = $registrations->pluck('serial_number')->toArray();
            // Find latest updated_at from all accounts
            foreach ($registrations as $registration) {
                if ($registration->loyaltyAccount && $registration->loyaltyAccount->updated_at) {
                    $accountUpdated = $registration->loyaltyAccount->updated_at->timestamp;
                    if ($accountUpdated > $latestUpdated) {
                        $latestUpdated = $accountUpdated;
                    }
                }
            }
        }

        // Use latest account updated_at, or current time if no accounts found
        $lastUpdated = $latestUpdated > 0 ? $latestUpdated : now()->timestamp;

        Log::info('Apple Wallet device updates list response', [
            'device_library_identifier' => $deviceLibraryIdentifier,
            'serial_count' => count($serialNumbers),
            'last_updated' => $lastUpdated,
            'serial_numbers' => $serialNumbers,
        ]);

        return response()->json([
            'lastUpdated' => (string) $lastUpdated,
            'serialNumbers' => $serialNumbers,
        ]);
    }

    /**
     * Log endpoint for Apple Wallet.
     * 
     * POST /wallet/v1/log
     * Note: This endpoint doesn't have a serial number, so authentication
     * must use the global web service auth token.
     */
    public function log(Request $request): Response
    {
        Log::debug('Apple Wallet log endpoint called', [
            'has_logs' => $request->has('logs'),
            'ip_address' => $request->ip(),
        ]);

        $logs = $request->input('logs', []);

        foreach ($logs as $log) {
            Log::info('Apple Wallet diagnostic log', [
                'level' => $log['level'] ?? 'info',
                'message' => $log['message'] ?? '',
                'data' => $log,
            ]);
        }

        return response('', 200);
    }
}
