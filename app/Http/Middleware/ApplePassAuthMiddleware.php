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
        $authHeader = $request->header('Authorization');

        if (!$authHeader) {
            \Log::warning('Apple Wallet Web Service: Missing Authorization header', [
                'path' => $request->path(),
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

        // For registration and pass retrieval, we need to validate against the pass's authenticationToken
        // Extract serial number from route if available
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
                    ]);
                    return $next($request);
                }
            } catch (\Exception $e) {
                \Log::warning('Apple Wallet authentication: Error resolving account', [
                    'serial_number' => $serialNumber,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fallback: Check against global web service auth token (for backward compatibility)
        $expectedToken = config('wallet.apple.web_service_auth_token');
        if ($expectedToken && $token === $expectedToken) {
            \Log::debug('Apple Wallet authentication: Token validated against global config');
            return $next($request);
        }

        \Log::warning('Apple Wallet authentication failed', [
            'path' => $request->path(),
            'serial_number' => $serialNumber ?? 'N/A',
            'provided_token_preview' => substr($token, 0, 10) . '...',
            'token_length' => strlen($token),
            'ip' => $request->ip(),
        ]);
        return response('Unauthorized', 401);
    }
}
