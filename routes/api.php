<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\ScannerController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->name('api.auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
        Route::get('/auth/me', [AuthController::class, 'me'])->name('api.auth.me');

        Route::get('/stores', [StoreController::class, 'index'])->name('api.stores.index');

        Route::middleware('verified')->group(function () {
            Route::post('/scanner/preview', [ScannerController::class, 'preview'])->name('api.scanner.preview');
            Route::post('/stamp', [ScannerController::class, 'store'])
                ->middleware('rate.limit.stamps')
                ->name('api.stamp.store');
            Route::post('/redeem/info', [ScannerController::class, 'getRedeemInfo'])->name('api.redeem.info');
            Route::post('/redeem', [ScannerController::class, 'redeem'])
                ->middleware('rate.limit.stamps')
                ->name('api.redeem.store');
        });
    });
});
