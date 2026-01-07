<?php

use App\Http\Controllers\CardController;
use App\Http\Controllers\JoinController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/join/{slug}', [JoinController::class, 'show'])->name('join.show');
Route::post('/join/{slug}', [JoinController::class, 'store'])->name('join.store');

Route::get('/c/{public_token}', [CardController::class, 'show'])->name('card.show');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/scanner', [ScannerController::class, 'index'])->name('scanner');
    Route::post('/stamp', [ScannerController::class, 'store'])->name('stamp.store');

    Route::resource('stores', StoreController::class);
    Route::get('/stores/{store}/qr', [StoreController::class, 'qr'])->name('stores.qr');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
