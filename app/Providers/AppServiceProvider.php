<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.env') === 'local') {
            URL::forceScheme('https');
        }

        // Fix asset URLs when accessing through ngrok or external URLs
        // This ensures Vite assets load correctly through ngrok
        $request = request();
        if ($request->header('x-forwarded-host') || str_contains($request->getHost(), 'ngrok')) {
            // Use the request's host for asset URLs when accessed through ngrok
            $host = $request->getHost();
            $scheme = $request->getScheme();
            $fullUrl = "{$scheme}://{$host}";
            config(['app.url' => $fullUrl]);
            
            // Force asset URL to use the ngrok URL
            URL::forceRootUrl($fullUrl);
        }

        // Super admin bypass for all gates and policies
        Gate::before(function ($user, $ability) {
            return $user->isSuperAdmin() ? true : null;
        });
    }
}
