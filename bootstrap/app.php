<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $trustedProxies = env('TRUSTED_PROXIES');
        $middleware->trustProxies(
            at: $trustedProxies
                ? array_values(array_filter(array_map('trim', explode(',', $trustedProxies))))
                : null
        );
        $middleware->alias([
            'rate.limit.stamps' => \App\Http\Middleware\RateLimitStamps::class,
            'superadmin' => \App\Http\Middleware\SuperAdmin::class,
            'merchant.has.store' => \App\Http\Middleware\EnsureMerchantHasStore::class,
        ]);
        $middleware->redirectGuestsTo('/login');
        $middleware->web(append: [\Illuminate\Session\Middleware\AuthenticateSession::class]);

        // Exclude Stripe webhook and Apple Wallet web service from CSRF verification
        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
            'wallet/v1/*',
            'c/*/verify-email/send',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Routing\Exceptions\InvalidSignatureException $exception, \Illuminate\Http\Request $request) {
            if (! $request->routeIs('verification.verify') || $request->expectsJson()) {
                return null;
            }

            // Only call an intact signed link expired; altered links stay invalid.
            $expired = \Illuminate\Support\Facades\URL::hasCorrectSignature($request, false)
                && ! \Illuminate\Support\Facades\URL::signatureHasNotExpired($request);

            return response()->view('auth.verification-link-invalid', compact('expired'), 403)
                ->header('Cache-Control', 'no-store')
                ->header('Referrer-Policy', 'no-referrer');
        });
    })->create();
