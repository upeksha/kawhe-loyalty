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

        // Exempt store management routes so merchants can create/view their first store
        $routeName = $request->route()?->getName();
        if ($routeName && (str_starts_with($routeName, 'merchant.stores.') || $routeName === 'merchant.stores.qr')) {
            return $next($request);
        }

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
