<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMerchantHasStore
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        // Super admins bypass this check
        if ($user && $user->isSuperAdmin()) {
            return $next($request);
        }

        // If merchant has no stores, redirect to onboarding
        if ($user && $user->stores()->count() === 0) {
            return redirect()->route('merchant.onboarding.store');
        }

        return $next($request);
    }
}
