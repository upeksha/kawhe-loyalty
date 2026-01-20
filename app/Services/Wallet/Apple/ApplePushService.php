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
                Log::info('Apple Wallet APNs JWT regenerated', [
                    'jwt_age_seconds' => $this->jwtGeneratedAt ? ($now - $this->jwtGeneratedAt) : 0,
                    'key_id' => $this->apnsKeyId,
                    'team_id' => $this->apnsTeamId,
                    'jwt_preview' => substr($this->cachedJWT, 0, 50) . '...',
                ]);
            } catch (\Exception $e) {
                Log::error('Apple Wallet APNs JWT generation failed', [
                    'registration_id' => $registration->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
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

        // Resolve key file path
        $authKeyFullPath = $this->apnsAuthKeyPath;
        if (!str_starts_with($authKeyFullPath, '/')) {
            // Relative path - resolve from storage
            $authKeyFullPath = storage_path('app/private/' . ltrim($this->apnsAuthKeyPath, '/'));
        }

        if (!file_exists($authKeyFullPath)) {
            Log::error('Apple Wallet APNs key file not found', [
                'configured_path' => $this->apnsAuthKeyPath,
                'resolved_path' => $authKeyFullPath,
                'file_exists' => file_exists($authKeyFullPath),
            ]);
            throw new \Exception("APNs auth key file not found: {$authKeyFullPath}");
        }
        
        if (!is_readable($authKeyFullPath)) {
            throw new \Exception("APNs auth key file is not readable: {$authKeyFullPath}");
        }

        // Read the .p8 key file
        $keyContent = file_get_contents($authKeyFullPath);
        if ($keyContent === false || empty($keyContent)) {
            throw new \Exception("Failed to read APNs auth key file: {$authKeyFullPath}");
        }
        
        // Log key file info for debugging (without exposing content)
        Log::debug('Apple Wallet APNs key file loaded', [
            'path' => $authKeyFullPath,
            'size' => strlen($keyContent),
            'starts_with' => substr($keyContent, 0, 30) . '...',
        ]);

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
            $opensslError = openssl_error_string();
            throw new \Exception('Failed to load APNs private key: ' . ($opensslError ?: 'Unknown error'));
        }

        $signature = '';
        if (!openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            $opensslError = openssl_error_string();
            throw new \Exception('Failed to sign JWT: ' . ($opensslError ?: 'Unknown error'));
        }

        // ES256 signature is in DER format, need to convert to R|S format (64 bytes)
        // OpenSSL returns DER format, we need to extract R and S (32 bytes each)
        $rsSignature = $this->derToRS($signature);
        
        if (strlen($rsSignature) !== 64) {
            Log::error('Apple Wallet APNs JWT signature length invalid', [
                'expected' => 64,
                'actual' => strlen($rsSignature),
                'der_length' => strlen($signature),
            ]);
            throw new \Exception('Invalid signature length after DER to R|S conversion');
        }
        
        $signatureEncoded = $this->base64UrlEncode($rsSignature);

        // Note: openssl_free_key is deprecated in PHP 8.0+, but safe to call
        if (function_exists('openssl_free_key')) {
            @openssl_free_key($privateKey);
        }

        return "{$headerEncoded}.{$payloadEncoded}.{$signatureEncoded}";
    }

    /**
     * Convert DER-encoded ECDSA signature to R|S format (64 bytes).
     *
     * @param string $der
     * @return string 64-byte string (32 bytes R + 32 bytes S)
     */
    protected function derToRS(string $der): string
    {
        // DER format: SEQUENCE { INTEGER r, INTEGER s }
        // We need to extract r and s, each padded to 32 bytes
        
        $r = '';
        $s = '';
        $offset = 0;
        $derLen = strlen($der);
        
        // Skip SEQUENCE header (0x30)
        if ($offset >= $derLen || ord($der[$offset]) !== 0x30) {
            throw new \Exception('Invalid DER format: expected SEQUENCE');
        }
        $offset++;
        
        // Skip length byte(s)
        $seqLen = ord($der[$offset]);
        $offset++;
        if ($seqLen & 0x80) {
            // Long form length
            $lenBytes = $seqLen & 0x7F;
            $seqLen = 0;
            for ($i = 0; $i < $lenBytes; $i++) {
                $seqLen = ($seqLen << 8) | ord($der[$offset]);
                $offset++;
            }
        }
        
        // Extract R (first INTEGER)
        if ($offset >= $derLen || ord($der[$offset]) !== 0x02) {
            throw new \Exception('Invalid DER format: expected INTEGER for R');
        }
        $offset++;
        
        $rLen = ord($der[$offset]);
        $offset++;
        
        // Handle long form length for R
        if ($rLen & 0x80) {
            $lenBytes = $rLen & 0x7F;
            $rLen = 0;
            for ($i = 0; $i < $lenBytes; $i++) {
                $rLen = ($rLen << 8) | ord($der[$offset]);
                $offset++;
            }
        }
        
        // Read R value
        $rBytes = substr($der, $offset, $rLen);
        $offset += $rLen;
        
        // Remove leading zero if present (for negative numbers, but we handle it)
        if (strlen($rBytes) > 0 && ord($rBytes[0]) === 0x00 && strlen($rBytes) > 32) {
            $rBytes = substr($rBytes, 1);
        }
        
        // Pad R to 32 bytes
        if (strlen($rBytes) > 32) {
            throw new \Exception('R value too long: ' . strlen($rBytes) . ' bytes');
        }
        $r = str_pad($rBytes, 32, "\0", STR_PAD_LEFT);
        
        // Extract S (second INTEGER)
        if ($offset >= $derLen || ord($der[$offset]) !== 0x02) {
            throw new \Exception('Invalid DER format: expected INTEGER for S');
        }
        $offset++;
        
        $sLen = ord($der[$offset]);
        $offset++;
        
        // Handle long form length for S
        if ($sLen & 0x80) {
            $lenBytes = $sLen & 0x7F;
            $sLen = 0;
            for ($i = 0; $i < $lenBytes; $i++) {
                $sLen = ($sLen << 8) | ord($der[$offset]);
                $offset++;
            }
        }
        
        // Read S value
        $sBytes = substr($der, $offset, $sLen);
        
        // Remove leading zero if present
        if (strlen($sBytes) > 0 && ord($sBytes[0]) === 0x00 && strlen($sBytes) > 32) {
            $sBytes = substr($sBytes, 1);
        }
        
        // Pad S to 32 bytes
        if (strlen($sBytes) > 32) {
            throw new \Exception('S value too long: ' . strlen($sBytes) . ' bytes');
        }
        $s = str_pad($sBytes, 32, "\0", STR_PAD_LEFT);
        
        return $r . $s;
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
