<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'rate.limit.stamps' => \App\Http\Middleware\RateLimitStamps::class,
            'superadmin' => \App\Http\Middleware\SuperAdmin::class,
            'merchant.has.store' => \App\Http\Middleware\EnsureMerchantHasStore::class,
        ]);
        
        // Exclude Stripe webhook and Apple Wallet web service from CSRF verification
        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
            'wallet/v1/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
