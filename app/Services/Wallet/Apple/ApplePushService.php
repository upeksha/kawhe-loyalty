<?php

namespace App\Services\Wallet\Apple;

use App\Models\AppleWalletRegistration;
use Illuminate\Support\Facades\Log;

/**
 * Service to send Apple Push Notifications for Wallet pass updates.
 */
class ApplePushService
{
    protected ?string $apnsKeyId = null;
    protected ?string $apnsTeamId = null;
    protected ?string $apnsAuthKeyPath = null;
    protected ?string $apnsTopic = null;
    protected bool $apnsProduction = true;
    protected bool $enabled = false;
    
    // JWT cache to avoid regenerating on every push (JWT expires after 1 hour, rebuild at 50 minutes)
    protected ?string $cachedJWT = null;
    protected ?int $jwtGeneratedAt = null;

    public function __construct()
    {
        $this->enabled = config('wallet.apple.push_enabled', false);
        $this->apnsKeyId = config('wallet.apple.apns_key_id');
        $this->apnsTeamId = config('wallet.apple.apns_team_id');
        $this->apnsAuthKeyPath = config('wallet.apple.apns_auth_key_path');
        $this->apnsTopic = config('wallet.apple.apns_topic');
        $this->apnsProduction = config('wallet.apple.apns_production', true);
    }

    /**
     * Send push notifications to all registered devices for a pass.
     *
     * @param string $passTypeIdentifier
     * @param string $serialNumber
     * @return void
     */
    public function sendPassUpdatePushes(string $passTypeIdentifier, string $serialNumber): void
    {
        Log::info('Apple Wallet push notification request received', [
            'pass_type_identifier' => $passTypeIdentifier,
            'serial_number' => $serialNumber,
            'push_enabled' => $this->enabled,
        ]);

        if (!$this->enabled) {
            Log::warning('Apple Wallet push notifications disabled by config', [
                'pass_type_identifier' => $passTypeIdentifier,
                'serial_number' => $serialNumber,
            ]);
            return;
        }

        // Find all active registrations for this pass
        // CRITICAL: Do NOT filter out null/empty - only filter by active=true
        $registrations = AppleWalletRegistration::where('pass_type_identifier', $passTypeIdentifier)
            ->where('serial_number', $serialNumber)
            ->where('active', true)
            ->get();

        Log::info('Apple Wallet registrations found', [
            'pass_type_identifier' => $passTypeIdentifier,
            'serial_number' => $serialNumber,
            'registrations_found' => $registrations->count(),
            'registration_ids' => $registrations->pluck('id')->toArray(),
        ]);

        if ($registrations->isEmpty()) {
            Log::warning('No active registrations found for pass update', [
                'pass_type_identifier' => $passTypeIdentifier,
                'serial_number' => $serialNumber,
            ]);
            return;
        }

        Log::info('Sending Apple Wallet push notifications', [
            'pass_type_identifier' => $passTypeIdentifier,
            'serial_number' => $serialNumber,
            'device_count' => $registrations->count(),
        ]);

        $successCount = 0;
        $failureCount = 0;

        foreach ($registrations as $registration) {
            try {
                Log::debug('Sending push to device', [
                    'registration_id' => $registration->id,
                    'device_library_identifier' => $registration->device_library_identifier,
                    'serial_number' => $serialNumber,
                    'push_token_length' => strlen($registration->push_token),
                ]);
                
                $this->sendPushNotification($registration);
                $successCount++;
                
                Log::info('Push notification sent successfully to device', [
                    'registration_id' => $registration->id,
                    'device_library_identifier' => $registration->device_library_identifier,
                ]);
            } catch (\Exception $e) {
                $failureCount++;
                Log::error('Failed to send Apple Wallet push notification', [
                    'registration_id' => $registration->id,
                    'device_library_identifier' => $registration->device_library_identifier,
                    'serial_number' => $serialNumber,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        Log::info('Apple Wallet push notifications batch completed', [
            'pass_type_identifier' => $passTypeIdentifier,
            'serial_number' => $serialNumber,
            'total_devices' => $registrations->count(),
            'successful' => $successCount,
            'failed' => $failureCount,
        ]);
    }

    /**
     * Send a single push notification to a device.
     *
     * @param AppleWalletRegistration $registration
     * @return void
     * @throws \Exception
     */
    protected function sendPushNotification(AppleWalletRegistration $registration): void
    {
        if (!$this->apnsKeyId || !$this->apnsTeamId || !$this->apnsAuthKeyPath || !$this->apnsTopic) {
            Log::warning('Apple Wallet APNs not fully configured, skipping push', [
                'registration_id' => $registration->id,
            ]);
            return;
        }

        // Validate auth key file exists
        $authKeyFullPath = $this->apnsAuthKeyPath;
        if (!str_starts_with($authKeyFullPath, '/')) {
            // Relative path - resolve from storage or base path
            $authKeyFullPath = storage_path('app/private/' . $this->apnsAuthKeyPath);
        }

        if (!file_exists($authKeyFullPath)) {
            Log::error('Apple Wallet APNs auth key file not found', [
                'path' => $authKeyFullPath,
                'registration_id' => $registration->id,
            ]);
            return;
        }

        // Generate JWT token for APNs authentication
        // Rebuild JWT if older than 50 minutes (JWT expires after 1 hour)
        $now = time();
        if (!$this->cachedJWT || !$this->jwtGeneratedAt || ($now - $this->jwtGeneratedAt) > 3000) {
            try {
                $this->cachedJWT = $this->generateJWT();
                $this->jwtGeneratedAt = $now;
                Log::debug('Apple Wallet APNs JWT regenerated', [
                    'jwt_age_seconds' => $this->jwtGeneratedAt ? ($now - $this->jwtGeneratedAt) : 0,
                ]);
            } catch (\Exception $e) {
                Log::error('Apple Wallet APNs JWT generation failed', [
                    'registration_id' => $registration->id,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }
        $jwt = $this->cachedJWT;

        // APNs endpoint - use sandbox if APPLE_APNS_USE_SANDBOX=true, otherwise production
        // Note: apns_production is inverted from APPLE_APNS_USE_SANDBOX in config
        $useSandbox = !$this->apnsProduction;
        $apnsUrl = $useSandbox
            ? 'https://api.sandbox.push.apple.com'
            : 'https://api.push.apple.com';

        $deviceToken = $registration->push_token;
        $url = "{$apnsUrl}/3/device/{$deviceToken}";

        // Payload for Wallet pass update (must be valid JSON with aps key)
        $payload = json_encode(['aps' => []]);

        // Headers
        $headers = [
            'Authorization: Bearer ' . $jwt,
            'apns-topic: ' . $this->apnsTopic,
            'apns-push-type: background',
            'apns-priority: 5', // Recommended priority for Wallet updates
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload),
        ];
        
        Log::debug('Apple Wallet APNs request prepared', [
            'registration_id' => $registration->id,
            'url' => $url,
            'topic' => $this->apnsTopic,
            'production' => $this->apnsProduction,
            'jwt_preview' => substr($jwt, 0, 50) . '...',
        ]);

        // Send HTTP/2 request using cURL
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($error) {
            Log::error('Apple Wallet APNs cURL error', [
                'registration_id' => $registration->id,
                'device_library_identifier' => $registration->device_library_identifier,
                'error' => $error,
            ]);
            throw new \Exception("cURL error: {$error}");
        }

        // Parse response headers and body
        $responseHeaders = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);
        
        // Parse JSON error response if present
        $errorData = null;
        if ($responseBody) {
            $errorData = json_decode($responseBody, true);
        }

        if ($httpCode === 200) {
            Log::info('Apple Wallet push notification sent successfully', [
                'registration_id' => $registration->id,
                'device_library_identifier' => $registration->device_library_identifier,
                'serial_number' => $registration->serial_number,
                'apns_id' => $this->extractApnsId($responseHeaders),
            ]);
        } else {
            // Enhanced error logging for 403 and other failures
            $apnsReason = $this->extractApnsReason($responseHeaders);
            $errorReason = $errorData['reason'] ?? null;
            
            $logData = [
                'registration_id' => $registration->id,
                'device_library_identifier' => $registration->device_library_identifier,
                'serial_number' => $registration->serial_number,
                'http_code' => $httpCode,
                'apns_reason' => $apnsReason,
                'response_body' => $responseBody,
                'response_body_full' => $responseBody, // Full body for debugging
                'response_body_length' => strlen($responseBody),
                'apns_topic' => $this->apnsTopic,
                'apns_url' => $apnsUrl,
                'apns_production' => $this->apnsProduction,
                'apns_key_id' => $this->apnsKeyId,
                'apns_team_id' => $this->apnsTeamId,
            ];
            
            if ($errorData) {
                $logData['error_reason'] = $errorReason;
                $logData['error_timestamp'] = $errorData['timestamp'] ?? null;
                $logData['error_data_full'] = $errorData; // Full error JSON
            }
            
            Log::error('Apple Wallet push notification failed - FULL APNs RESPONSE', $logData);

            // If device token is invalid, deactivate registration
            if ($httpCode === 410) {
                $registration->update(['active' => false]);
                Log::info('Deactivated registration due to invalid device token', [
                    'registration_id' => $registration->id,
                    'serial_number' => $registration->serial_number,
                ]);
                // Don't throw - allow other registrations to continue
                return;
            }
            
            // For 403 errors, log but don't throw (allow other pushes to continue)
            if ($httpCode === 403) {
                $reason = $apnsReason ?? $errorReason ?? 'Unknown';
                Log::error('APNs 403 Forbidden - Authentication failed', [
                    'registration_id' => $registration->id,
                    'reason' => $reason,
                    'apns_topic' => $this->apnsTopic,
                    'apns_url' => $apnsUrl,
                    'full_error_response' => $errorData,
                    'suggestion' => 'Check APNs key permissions in Apple Developer Portal. Topic must match Pass Type Identifier exactly: ' . $this->apnsTopic,
                ]);
                // Don't throw - continue with other registrations
                return;
            }
        }
    }

    /**
     * Generate JWT token for APNs authentication.
     *
     * @return string
     * @throws \Exception
     */
    protected function generateJWT(): string
    {
        if (!$this->apnsKeyId || !$this->apnsTeamId || !$this->apnsAuthKeyPath) {
            throw new \Exception('APNs configuration incomplete');
        }

        $authKeyFullPath = $this->apnsAuthKeyPath;
        if (!str_starts_with($authKeyFullPath, '/')) {
            $authKeyFullPath = storage_path('app/private/' . $this->apnsAuthKeyPath);
        }

        if (!file_exists($authKeyFullPath)) {
            throw new \Exception("APNs auth key file not found: {$authKeyFullPath}");
        }

        // Read the .p8 key file
        $keyContent = file_get_contents($authKeyFullPath);
        if (!$keyContent) {
            throw new \Exception("Failed to read APNs auth key file");
        }

        // JWT header
        $header = [
            'alg' => 'ES256',
            'kid' => $this->apnsKeyId,
        ];

        // JWT payload
        $now = time();
        $payload = [
            'iss' => $this->apnsTeamId,
            'iat' => $now,
        ];

        // Encode header and payload
        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));

        // Create signature
        $signatureInput = "{$headerEncoded}.{$payloadEncoded}";
        
        // Sign with ES256 (ECDSA P-256 SHA-256)
        $privateKey = openssl_pkey_get_private($keyContent);
        if (!$privateKey) {
            throw new \Exception('Failed to load APNs private key: ' . openssl_error_string());
        }

        $signature = '';
        if (!openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \Exception('Failed to sign JWT: ' . openssl_error_string());
        }

        // ES256 signature is in DER format, need to convert to R|S format (64 bytes)
        // OpenSSL returns DER format, we need to extract R and S (32 bytes each)
        $der = $signature;
        $r = '';
        $s = '';
        
        // Parse DER to extract R and S
        // This is a simplified parser - for production, consider using a library
        $offset = 4; // Skip DER header
        if (ord($der[$offset]) === 0x02) { // INTEGER tag
            $rLen = ord($der[$offset + 1]);
            $rStart = $offset + 2;
            if ($rLen > 32) {
                // Skip leading zero if present
                $rStart++;
                $rLen--;
            }
            $r = substr($der, $rStart, min(32, $rLen));
            if (strlen($r) < 32) {
                $r = str_pad($r, 32, "\0", STR_PAD_LEFT);
            }
            $offset += 2 + ord($der[$offset + 1]);
        }
        
        if (ord($der[$offset]) === 0x02) { // INTEGER tag
            $sLen = ord($der[$offset + 1]);
            $sStart = $offset + 2;
            if ($sLen > 32) {
                $sStart++;
                $sLen--;
            }
            $s = substr($der, $sStart, min(32, $sLen));
            if (strlen($s) < 32) {
                $s = str_pad($s, 32, "\0", STR_PAD_LEFT);
            }
        }
        
        // Combine R and S (64 bytes total)
        $rsSignature = $r . $s;
        $signatureEncoded = $this->base64UrlEncode($rsSignature);

        // Note: openssl_free_key is deprecated in PHP 8.0+, but safe to call
        if (function_exists('openssl_free_key')) {
            @openssl_free_key($privateKey);
        }

        return "{$headerEncoded}.{$payloadEncoded}.{$signatureEncoded}";
    }

    /**
     * Base64 URL encode (RFC 4648).
     *
     * @param string $data
     * @return string
     */
    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Extract APNs ID from response headers.
     *
     * @param string $headers
     * @return string|null
     */
    protected function extractApnsId(string $headers): ?string
    {
        if (preg_match('/apns-id:\s*([^\r\n]+)/i', $headers, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    /**
     * Extract APNs reason from response headers.
     *
     * @param string $headers
     * @return string|null
     */
    protected function extractApnsReason(string $headers): ?string
    {
        if (preg_match('/apns-reason:\s*([^\r\n]+)/i', $headers, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }
}
