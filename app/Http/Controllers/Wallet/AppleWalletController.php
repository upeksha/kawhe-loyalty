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
        // CRITICAL: Ensure serial_number matches exactly what's in pass.json
        $registration = AppleWalletRegistration::updateOrCreate(
            [
                'device_library_identifier' => $deviceLibraryIdentifier,
                'pass_type_identifier' => $passTypeIdentifier,
                'serial_number' => $serialNumber, // Must match pass.json serialNumber exactly
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
            'registration_saved' => true,
            'active' => $registration->active,
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
                $lastModified = $account->updated_at->toRfc7231String();
                Log::debug('Apple Wallet pass: 304 Not Modified', [
                    'serial_number' => $serialNumber,
                    'if_modified_since' => $ifModifiedSince,
                    'account_updated_at' => $lastModified,
                ]);
                
                // Return 304 with required headers
                // Using withHeaders() to ensure headers are set correctly
                return response('', 304)->withHeaders([
                    'Last-Modified' => $lastModified,
                    'Cache-Control' => 'no-store',
                ]);
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

            Log::info('Apple Wallet pass generated and served for web service', [
                'serial_number' => $serialNumber,
                'loyalty_account_id' => $account->id,
                'stamp_count' => $account->stamp_count,
                'reward_balance' => $account->reward_balance ?? 0,
                'size' => strlen($pkpassData),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response($pkpassData, 200)->withHeaders([
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
     * 
     * Returns only serial numbers where the pass actually changed since passesUpdatedSince.
     * Uses STRICT comparison (updated_at > passesUpdatedSince) to avoid false positives.
     */
    public function getUpdatedSerials(Request $request, string $deviceLibraryIdentifier, string $passTypeIdentifier): Response|JsonResponse
    {
        try {
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
            $updatedTimestamps = [];

            if ($passesUpdatedSince) {
                // Normalize passesUpdatedSince: replace spaces with '+' (URL decoding issue)
                // Apple may send "2026-01-20T21:52:23 00:00" instead of "2026-01-20T21:52:23+00:00"
                $normalizedSince = str_replace(' ', '+', $passesUpdatedSince);
                
                Log::debug('Apple Wallet device updates: Parsing passesUpdatedSince', [
                    'raw' => $passesUpdatedSince,
                    'normalized' => $normalizedSince,
                ]);

                // Parse passesUpdatedSince timestamp (can be ISO8601 string or Unix timestamp)
                try {
                    // Try parsing as ISO8601 first (Apple's preferred format)
                    $since = \Carbon\Carbon::parse($normalizedSince);
                    // Ensure UTC timezone for consistent comparison
                    $since = $since->utc();
                    
                    Log::debug('Apple Wallet device updates: Successfully parsed timestamp', [
                        'parsed' => $since->toIso8601String(),
                        'utc' => $since->utc()->toIso8601String(),
                    ]);
                } catch (\Exception $e) {
                    // Fallback to numeric timestamp
                    if (is_numeric($passesUpdatedSince)) {
                        $since = \Carbon\Carbon::createFromTimestamp((int) $passesUpdatedSince)->utc();
                        Log::debug('Apple Wallet device updates: Parsed as Unix timestamp', [
                            'timestamp' => $passesUpdatedSince,
                            'parsed' => $since->toIso8601String(),
                        ]);
                    } else {
                        // Invalid timestamp, log and return empty (safer than returning all)
                        Log::warning('Apple Wallet device updates list: Invalid passesUpdatedSince timestamp', [
                            'device_library_identifier' => $deviceLibraryIdentifier,
                            'pass_type_identifier' => $passTypeIdentifier,
                            'passes_updated_since_raw' => $passesUpdatedSince,
                            'passes_updated_since_normalized' => $normalizedSince,
                            'error' => $e->getMessage(),
                        ]);
                        // Return JSON with empty array and lastUpdated in Zulu format
                        return response()->json([
                            'lastUpdated' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
                            'serialNumbers' => [],
                        ]);
                    }
                }

                // Filter registrations: only include if LoyaltyAccount->updated_at > passesUpdatedSince (STRICT)
                foreach ($registrations as $registration) {
                    if (!$registration->loyaltyAccount) {
                        // Skip registrations without accounts
                        continue;
                    }

                    $account = $registration->loyaltyAccount;
                    
                    // Ensure account has updated_at
                    if (!$account->updated_at) {
                        continue;
                    }

                    // Use STRICT comparison: updated_at must be strictly greater than passesUpdatedSince
                    // This ensures we only return passes that actually changed
                    $accountUpdated = $account->updated_at->utc();
                    
                    // Log comparison for debugging
                    $isNewer = $accountUpdated->gt($since);
                    Log::debug('Apple Wallet device updates: Checking account', [
                        'serial_number' => $registration->serial_number,
                        'account_updated_at' => $accountUpdated->format('Y-m-d\TH:i:s\Z'),
                        'account_updated_at_iso' => $accountUpdated->toIso8601String(),
                        'passes_updated_since' => $since->format('Y-m-d\TH:i:s\Z'),
                        'passes_updated_since_iso' => $since->toIso8601String(),
                        'is_newer' => $isNewer,
                        'difference_seconds' => $accountUpdated->diffInSeconds($since, false),
                        'included' => $isNewer,
                    ]);
                    
                    if ($isNewer) {
                        // Account was updated AFTER passesUpdatedSince - include it
                        $serialNumbers[] = $registration->serial_number;
                        $updatedTimestamps[] = $accountUpdated;
                        Log::debug('Apple Wallet device updates: Serial included', [
                            'serial_number' => $registration->serial_number,
                            'account_id' => $account->id,
                        ]);
                    }
                }

                // If no serials changed, return JSON with empty array and lastUpdated = passesUpdatedSince
                if (empty($serialNumbers)) {
                    Log::info('Apple Wallet device updates list: No updates found', [
                        'device_library_identifier' => $deviceLibraryIdentifier,
                        'pass_type_identifier' => $passTypeIdentifier,
                        'passes_updated_since_raw' => $passesUpdatedSince,
                        'since_parsed' => $since->format('Y-m-d\TH:i:s\Z'),
                        'total_registrations' => $registrations->count(),
                    ]);
                    
                    // Return 204 No Content when no updates
                    return response()->noContent();
                }

                // Calculate lastUpdated as max updated_at from the returned serials
                if (!empty($updatedTimestamps)) {
                    $lastUpdated = collect($updatedTimestamps)->max();
                    // Ensure we have a valid Carbon instance
                    if (!$lastUpdated instanceof \Carbon\Carbon) {
                        $lastUpdated = now()->utc();
                    }
                } else {
                    // Fallback: use passesUpdatedSince if no timestamps (shouldn't happen, but safety)
                    $lastUpdated = $since;
                }
            } else {
                // No passesUpdatedSince provided - return all active registrations
                $serialNumbers = $registrations->pluck('serial_number')->toArray();
                
                // Find latest updated_at from all accounts
                foreach ($registrations as $registration) {
                    if ($registration->loyaltyAccount && $registration->loyaltyAccount->updated_at) {
                        $updatedTimestamps[] = $registration->loyaltyAccount->updated_at->utc();
                    }
                }
                
                if (!empty($updatedTimestamps)) {
                    $lastUpdated = collect($updatedTimestamps)->max();
                    // Ensure we have a valid Carbon instance
                    if (!$lastUpdated instanceof \Carbon\Carbon) {
                        $lastUpdated = now()->utc();
                    }
                } else {
                    $lastUpdated = now()->utc();
                }
            }

            // Convert to Zulu format (Z) instead of +00:00 to avoid URL encoding issues
            // Apple expects ISO8601 but Zulu format (Z) is more reliable for URL parameters
            $lastUpdatedISO = $lastUpdated->format('Y-m-d\TH:i:s\Z');

            Log::info('Apple Wallet device updates list response', [
                'device_library_identifier' => $deviceLibraryIdentifier,
                'serial_count' => count($serialNumbers),
                'last_updated_zulu' => $lastUpdatedISO,
                'last_updated_iso' => $lastUpdated->toIso8601String(),
                'serial_numbers' => $serialNumbers,
            ]);

            return response()->json([
                'lastUpdated' => $lastUpdatedISO,
                'serialNumbers' => $serialNumbers,
            ]);
        } catch (\Exception $e) {
            Log::error('Apple Wallet device updates list error', [
                'device_library_identifier' => $deviceLibraryIdentifier,
                'pass_type_identifier' => $passTypeIdentifier,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Return empty response on error (safer than crashing)
            // Use Zulu format (Z) instead of +00:00 to avoid URL encoding issues
            return response()->json([
                'lastUpdated' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
                'serialNumbers' => [],
            ], 500);
        }
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
