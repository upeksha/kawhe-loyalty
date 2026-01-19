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

        $expectedToken = config('wallet.apple.web_service_auth_token');

        if (!$expectedToken) {
            \Log::error('Apple Wallet web service auth token not configured');
            return response('Server configuration error', 500);
        }

        if ($token !== $expectedToken) {
            \Log::warning('Apple Wallet authentication failed', [
                'provided_token_preview' => substr($token, 0, 10) . '...',
                'expected_token_preview' => substr($expectedToken, 0, 10) . '...',
            ]);
            return response('Unauthorized', 401);
        }

        return $next($request);
    }
}
