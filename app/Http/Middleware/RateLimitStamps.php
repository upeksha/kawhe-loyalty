<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitStamps
{
    /**
     * Handle an incoming request.
     * 
     * Rate limits:
     * - Per customer: 10 stamps per minute
     * - Per store: 100 stamps per minute
     * - Per IP: 50 stamps per minute
     */
    public function handle(Request $request, Closure $next): Response
    {
        $storeId = $request->input('store_id');
        $token = $request->input('token');
        $ipAddress = $request->ip();

        // Strip "LA:" prefix if present for rate limiting key
        if ($token && str_starts_with($token, 'LA:')) {
            $token = substr($token, 3);
        }

        // Per-customer rate limit (if token provided)
        if ($token) {
            $customerKey = "stamp:customer:{$token}";
            $customerLimit = RateLimiter::attempt(
                $customerKey,
                10, // 10 stamps
                function () {},
                60 // per minute
            );

            if (!$customerLimit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many stamps for this customer. Please wait a moment.',
                ], 429);
            }
        }

        // Per-store rate limit
        if ($storeId) {
            $storeKey = "stamp:store:{$storeId}";
            $storeLimit = RateLimiter::attempt(
                $storeKey,
                100, // 100 stamps
                function () {},
                60 // per minute
            );

            if (!$storeLimit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Store rate limit exceeded. Please try again later.',
                ], 429);
            }
        }

        // Per-IP rate limit
        $ipKey = "stamp:ip:{$ipAddress}";
        $ipLimit = RateLimiter::attempt(
            $ipKey,
            50, // 50 stamps
            function () {},
            60 // per minute
        );

        if (!$ipLimit) {
            return response()->json([
                'success' => false,
                'message' => 'Too many requests from this IP. Please wait a moment.',
            ], 429);
        }

        return $next($request);
    }
}
