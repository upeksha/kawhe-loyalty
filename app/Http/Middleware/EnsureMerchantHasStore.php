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

        $routeName = $request->route()?->getName();

        // Exempt onboarding v2 wizard routes
        if ($routeName && str_starts_with($routeName, 'merchant.onboarding.wizard.')) {
            return $next($request);
        }

        // Super admins bypass this check
        if ($user && $user->isSuperAdmin()) {
            return $next($request);
        }

        // If merchant has no stores, redirect to onboarding v2
        if ($user && $user->stores()->count() === 0) {
            return redirect()->route('merchant.onboarding.wizard.store-basics');
        }

        // If first store is still in onboarding (has onboarding_step set), redirect to wizard
        $onboardingStore = $user->stores()
            ->whereNotNull('onboarding_step')
            ->whereNull('onboarding_completed_at')
            ->orderBy('id')
            ->first();
        if ($onboardingStore) {
            return redirect()->route('merchant.onboarding.wizard.index');
        }

        return $next($request);
    }
}
