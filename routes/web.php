<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\JoinController;
use App\Http\Controllers\MerchantCustomersController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicStartController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\StoreController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public start page for merchant onboarding
Route::get('/start', [PublicStartController::class, 'index'])->name('start');

Route::get('/', function () {
    return view('welcome');
});

Route::get('/join/{slug}', [JoinController::class, 'index'])->name('join.index');
Route::get('/join/{slug}/new', [JoinController::class, 'show'])->name('join.show');
Route::post('/join/{slug}/new', [JoinController::class, 'store'])->name('join.store');
Route::get('/join/{slug}/existing', [JoinController::class, 'existing'])->name('join.existing');
Route::post('/join/{slug}/existing', [JoinController::class, 'lookup'])->name('join.lookup')->middleware('throttle:10,1');

Route::get('/c/{public_token}', [CardController::class, 'show'])->name('card.show');
Route::get('/api/card/{public_token}', [CardController::class, 'api'])->name('card.api');
Route::get('/api/card/{public_token}/transactions', [CardController::class, 'transactions'])->name('card.transactions');

// Apple Wallet pass download (signed URL for security)
Route::get('/wallet/apple/{public_token}/download', [App\Http\Controllers\WalletController::class, 'downloadApplePass'])
    ->name('wallet.apple.download')
    ->middleware('signed');

// Google Wallet save link (signed URL for security)
Route::get('/wallet/google/{public_token}/save', [App\Http\Controllers\WalletController::class, 'saveGooglePass'])
    ->name('wallet.google.save')
    ->middleware('signed');

Route::post('/c/{public_token}/verify/send', [App\Http\Controllers\VerificationController::class, 'send'])->name('card.verification.send');
Route::get('/c/{public_token}/verify/{id}/{hash}', [App\Http\Controllers\VerificationController::class, 'verify'])->name('card.verification.verify')->middleware('signed');

Route::post('/c/{public_token}/verify-email/send', [App\Http\Controllers\CustomerEmailVerificationController::class, 'send'])->name('customer.email.verification.send')->middleware('throttle:3,10');
Route::get('/verify-email/{token}', [App\Http\Controllers\CustomerEmailVerificationController::class, 'verify'])->name('customer.email.verification.verify');

// Merchant onboarding routes (no EnsureMerchantHasStore middleware)
Route::middleware(['auth'])->prefix('merchant/onboarding')->name('merchant.onboarding.')->group(function () {
    Route::get('/store', [OnboardingController::class, 'createStore'])->name('store');
    Route::post('/store', [OnboardingController::class, 'storeStore'])->name('store.store');
});

// Merchant area routes (requires store)
Route::middleware(['auth', App\Http\Middleware\EnsureMerchantHasStore::class])->prefix('merchant')->name('merchant.')->group(function () {
    Route::get('/dashboard', function (Request $request) {
        $usageService = app(\App\Services\Billing\UsageService::class);
        $stats = $usageService->getUsageStats($request->user());
        return view('dashboard', ['usageStats' => $stats]);
    })->name('dashboard');
    
    Route::get('/stores', [StoreController::class, 'index'])->name('stores.index');
    Route::get('/stores/create', [StoreController::class, 'create'])->name('stores.create');
    Route::post('/stores', [StoreController::class, 'store'])->name('stores.store');
    Route::get('/stores/{store}/edit', [StoreController::class, 'edit'])->name('stores.edit');
    Route::put('/stores/{store}', [StoreController::class, 'update'])->name('stores.update');
    Route::delete('/stores/{store}', [StoreController::class, 'destroy'])->name('stores.destroy');
    Route::get('/stores/{store}/qr', [StoreController::class, 'qr'])->name('stores.qr');
    
    Route::get('/scanner', [ScannerController::class, 'index'])->name('scanner');
    
    Route::get('/customers', [MerchantCustomersController::class, 'index'])->name('customers.index');
    Route::get('/customers/{loyaltyAccount}', [MerchantCustomersController::class, 'show'])->name('customers.show');
});

// Scanner actions (keep outside merchant group to avoid double middleware)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/stamp', [ScannerController::class, 'store'])->middleware('rate.limit.stamps')->name('stamp.store');
    Route::post('/redeem/info', [ScannerController::class, 'getRedeemInfo'])->name('redeem.info');
    Route::post('/redeem', [ScannerController::class, 'redeem'])->middleware('rate.limit.stamps')->name('redeem.store');
});

// Admin area routes (super admin only)
Route::middleware(['auth', App\Http\Middleware\SuperAdmin::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
});

// Redirects from old routes to new merchant routes
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', fn() => redirect()->route('merchant.dashboard'));
    Route::get('/stores', fn() => redirect()->route('merchant.stores.index'));
    Route::get('/scanner', fn() => redirect()->route('merchant.scanner'));
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // Billing routes
    Route::get('/billing', [App\Http\Controllers\BillingController::class, 'index'])->name('billing.index');
    Route::post('/billing/checkout', [App\Http\Controllers\BillingController::class, 'checkout'])->name('billing.checkout');
    Route::post('/billing/portal', [App\Http\Controllers\BillingController::class, 'portal'])->name('billing.portal');
    Route::get('/billing/success', [App\Http\Controllers\BillingController::class, 'success'])->name('billing.success');
    Route::post('/billing/sync', [App\Http\Controllers\BillingController::class, 'sync'])->name('billing.sync');
    Route::get('/billing/cancel', [App\Http\Controllers\BillingController::class, 'cancel'])->name('billing.cancel');
});

// Stripe webhook (must be outside auth middleware, handled by Cashier)
Route::post('/stripe/webhook', [\Laravel\Cashier\Http\Controllers\WebhookController::class, 'handleWebhook'])->name('cashier.webhook');

// Apple Wallet Pass Web Service endpoints
// These routes are public but protected by ApplePassAuthMiddleware
// They must be excluded from CSRF verification (handled in bootstrap/app.php)
Route::prefix('wallet/v1')->middleware([App\Http\Middleware\ApplePassAuthMiddleware::class])->group(function () {
    // Register device for pass updates
    Route::post('/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}/{serialNumber}', 
        [App\Http\Controllers\Wallet\AppleWalletController::class, 'registerDevice']);
    
    // Unregister device
    Route::delete('/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}/{serialNumber}', 
        [App\Http\Controllers\Wallet\AppleWalletController::class, 'unregisterDevice']);
    
    // Get updated pass file
    Route::get('/passes/{passTypeIdentifier}/{serialNumber}', 
        [App\Http\Controllers\Wallet\AppleWalletController::class, 'getPass']);
    
    // Get list of updated serial numbers for a device
    Route::get('/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}', 
        [App\Http\Controllers\Wallet\AppleWalletController::class, 'getUpdatedSerials']);
    
    // Log endpoint
    Route::post('/log', [App\Http\Controllers\Wallet\AppleWalletController::class, 'log']);
});

require __DIR__.'/auth.php';
