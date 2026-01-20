<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplePassAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();
        
        // Endpoints that don't require authentication:
        // - GET /wallet/v1/devices/{device}/registrations/{passType} (device updates list)
        // - POST /wallet/v1/log (logging endpoint)
        $noAuthPaths = [
            'wallet/v1/devices/',
            'wallet/v1/log',
        ];
        
        $requiresAuth = true;
        foreach ($noAuthPaths as $noAuthPath) {
            if (str_contains($path, $noAuthPath)) {
                // Special handling for device registrations list
                if (str_contains($path, 'wallet/v1/devices/') && !$request->route('serialNumber')) {
                    // This is GET /devices/{device}/registrations/{passType} (no serial)
                    $requiresAuth = false;
                    break;
                }
                // POST /wallet/v1/log doesn't require auth
                if (str_contains($path, 'wallet/v1/log') && $request->isMethod('POST')) {
                    $requiresAuth = false;
                    break;
                }
            }
        }
        
        if (!$requiresAuth) {
            \Log::debug('Apple Wallet Web Service: Skipping auth for public endpoint', [
                'path' => $path,
                'method' => $request->method(),
            ]);
            return $next($request);
        }

        $authHeader = $request->header('Authorization');

        if (!$authHeader) {
            \Log::warning('Apple Wallet Web Service: Missing Authorization header', [
                'path' => $path,
                'ip' => $request->ip(),
            ]);
            return response('Unauthorized', 401);
        }

        // Apple sends: Authorization: ApplePass <token>
        // Some clients may send: Authorization: <token>
        $token = null;
        if (str_starts_with($authHeader, 'ApplePass ')) {
            $token = substr($authHeader, 10); // Remove "ApplePass " prefix
        } else {
            $token = $authHeader;
        }

        // For endpoints with serial numbers (registration, pass retrieval), validate against pass's public_token
        // For endpoints without serial numbers (log), use global token
        $serialNumber = $request->route('serialNumber');
        
        if ($serialNumber) {
            // Try to resolve the account and validate token against its public_token
            try {
                $passService = app(\App\Services\Wallet\Apple\ApplePassService::class);
                $account = $passService->resolveLoyaltyAccount($serialNumber);
                
                if ($account && $account->public_token === $token) {
                    // Token matches the pass's public_token (which is used as authenticationToken)
                    \Log::debug('Apple Wallet authentication: Token validated against pass', [
                        'serial_number' => $serialNumber,
                        'loyalty_account_id' => $account->id,
                        'path' => $request->path(),
                    ]);
                    return $next($request);
                } else if ($account) {
                    \Log::debug('Apple Wallet authentication: Token mismatch for pass', [
                        'serial_number' => $serialNumber,
                        'loyalty_account_id' => $account->id,
                        'expected_token_preview' => substr($account->public_token, 0, 10) . '...',
                        'provided_token_preview' => substr($token, 0, 10) . '...',
                    ]);
                }
            } catch (\Exception $e) {
                \Log::warning('Apple Wallet authentication: Error resolving account', [
                    'serial_number' => $serialNumber,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fallback: Check against global web service auth token
        // This is used for:
        // 1. Endpoints without serial numbers (like /log)
        // 2. Backward compatibility
        $expectedToken = config('wallet.apple.web_service_auth_token');
        if ($expectedToken && $token === $expectedToken) {
            \Log::debug('Apple Wallet authentication: Token validated against global config', [
                'path' => $request->path(),
                'has_serial' => !empty($serialNumber),
            ]);
            return $next($request);
        }

        \Log::warning('Apple Wallet authentication failed', [
            'path' => $request->path(),
            'serial_number' => $serialNumber ?? 'N/A',
            'provided_token_preview' => substr($token, 0, 20) . '...',
            'token_length' => strlen($token),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        return response('Unauthorized', 401);
    }
}
