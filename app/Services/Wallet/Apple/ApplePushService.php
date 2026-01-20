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
        if (!$this->enabled) {
            Log::debug('Apple Wallet push notifications disabled by config', [
                'pass_type_identifier' => $passTypeIdentifier,
                'serial_number' => $serialNumber,
            ]);
            return;
        }

        // Find all active registrations for this pass
        $registrations = AppleWalletRegistration::where('pass_type_identifier', $passTypeIdentifier)
            ->where('serial_number', $serialNumber)
            ->where('active', true)
            ->get();

        if ($registrations->isEmpty()) {
            Log::debug('No active registrations found for pass update', [
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
                $this->sendPushNotification($registration);
                $successCount++;
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

        Log::info('Apple Wallet push notifications completed', [
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
        $jwt = $this->generateJWT();

        // APNs endpoint
        $apnsUrl = $this->apnsProduction
            ? 'https://api.push.apple.com'
            : 'https://api.sandbox.push.apple.com';

        $deviceToken = $registration->push_token;
        $url = "{$apnsUrl}/3/device/{$deviceToken}";

        // Payload for Wallet pass update (must be valid JSON with aps key)
        $payload = json_encode(['aps' => []]);

        // Headers
        $headers = [
            'Authorization: Bearer ' . $jwt,
            'apns-topic: ' . $this->apnsTopic,
            'apns-push-type: background',
            'apns-priority: 10',
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload),
        ];

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

        if ($httpCode === 200) {
            Log::info('Apple Wallet push notification sent successfully', [
                'registration_id' => $registration->id,
                'device_library_identifier' => $registration->device_library_identifier,
                'serial_number' => $registration->serial_number,
                'apns_id' => $this->extractApnsId($responseHeaders),
            ]);
        } else {
            Log::warning('Apple Wallet push notification failed', [
                'registration_id' => $registration->id,
                'device_library_identifier' => $registration->device_library_identifier,
                'serial_number' => $registration->serial_number,
                'http_code' => $httpCode,
                'response_body' => $responseBody,
                'apns_reason' => $this->extractApnsReason($responseHeaders),
            ]);

            // If device token is invalid, deactivate registration
            if ($httpCode === 410) {
                $registration->update(['active' => false]);
                Log::info('Deactivated registration due to invalid device token', [
                    'registration_id' => $registration->id,
                    'serial_number' => $registration->serial_number,
                ]);
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
