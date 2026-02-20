<?php

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

// Dashboard route - conditional redirect (Filament panels)
Route::middleware(['auth'])->get('/dashboard', function (Request $request) {
    $user = $request->user();
    if ($user->is_super_admin) {
        return redirect('/admin');
    }
    if ($user->stores()->count() > 0) {
        return redirect('/merchant');
    }
    return view('dashboard');
})->name('dashboard');

// Legacy redirects (no store: send to onboarding)
Route::middleware(['auth'])->group(function () {
    Route::get('/stores', fn () => redirect()->route('merchant.stores.index'));
    Route::get('/scanner', fn () => redirect()->route('merchant.scanner'));
});

// Short join URL: /j/{code} -> redirect to full join flow
Route::get('/j/{code}', [JoinController::class, 'shortRedirect'])->name('join.short');

Route::get('/join/{slug}', [JoinController::class, 'index'])->name('join.index');
Route::get('/join/{slug}/new', [JoinController::class, 'show'])->name('join.show');
Route::post('/join/{slug}/new', [JoinController::class, 'store'])->name('join.store');
Route::get('/join/{slug}/existing', [JoinController::class, 'existing'])->name('join.existing');
Route::post('/join/{slug}/existing', [JoinController::class, 'lookup'])->name('join.lookup')->middleware('throttle:10,1');

Route::get('/c/{public_token}', [CardController::class, 'show'])->name('card.show');
Route::get('/c/{public_token}/manifest.webmanifest', [CardController::class, 'manifest'])->name('card.manifest');
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

// Merchant: named routes for backwards compatibility (redirect to Filament) + QR/PDF + legacy scanner (same backend)
// Use /merchant/legacy/* so Filament can own /merchant, /merchant/stores, etc.
Route::middleware(['auth', App\Http\Middleware\EnsureMerchantHasStore::class])->prefix('merchant/legacy')->name('merchant.')->group(function () {
    Route::get('/dashboard', fn () => redirect('/merchant'))->name('dashboard');
    Route::get('/stores', fn () => redirect('/merchant/stores'))->name('stores.index');
    Route::get('/stores/create', fn () => redirect('/merchant/stores/create'))->name('stores.create');
    Route::get('/stores/{store}/edit', fn (App\Models\Store $store) => redirect('/merchant/stores/'.$store->id.'/edit'))->name('stores.edit');
    Route::get('/customers', function () {
    $query = request()->getQueryString();
    return redirect('/merchant/customers'.($query ? '?'.$query : ''));
})->name('customers.index');
    Route::get('/customers/{loyaltyAccount}', fn (App\Models\LoyaltyAccount $loyaltyAccount) => redirect('/merchant/customers/'.$loyaltyAccount->id.'/edit'))->name('customers.show');
    Route::get('/customers/{loyaltyAccount}/edit', fn (App\Models\LoyaltyAccount $loyaltyAccount) => redirect('/merchant/customers/'.$loyaltyAccount->id.'/edit'))->name('customers.edit');
    Route::get('/scanner', fn () => redirect('/merchant/scanner'))->name('scanner');
});
// Legacy scanner UI (reused by Filament Scanner page iframe; path distinct from redirect)
Route::middleware(['auth', App\Http\Middleware\EnsureMerchantHasStore::class])->get('/merchant/legacy/scanner-ui', [ScannerController::class, 'index']);
Route::middleware(['auth', App\Http\Middleware\EnsureMerchantHasStore::class])->prefix('merchant')->name('merchant.')->group(function () {
    Route::get('/stores/{store}/qr', [StoreController::class, 'qr'])->name('stores.qr');
    Route::get('/stores/{store}/qr/pdf', [StoreController::class, 'qrPdf'])->name('stores.qr.pdf');
});

// Scanner actions (keep outside merchant group to avoid double middleware)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/scanner/preview', [ScannerController::class, 'preview'])->name('scanner.preview');
    Route::post('/stamp', [ScannerController::class, 'store'])->middleware('rate.limit.stamps')->name('stamp.store');
    Route::post('/redeem/info', [ScannerController::class, 'getRedeemInfo'])->name('redeem.info');
    Route::post('/redeem', [ScannerController::class, 'redeem'])->middleware('rate.limit.stamps')->name('redeem.store');
});

// Admin: backwards-compatible named route (redirect to Filament)
Route::middleware(['auth', App\Http\Middleware\SuperAdmin::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', fn () => redirect('/admin'))->name('dashboard');
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
