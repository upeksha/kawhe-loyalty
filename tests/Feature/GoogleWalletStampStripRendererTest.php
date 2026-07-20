<?php

use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Services\Wallet\GoogleWalletStampStripRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('generates stamp strip image using loyalty program settings and account progress', function () {
    if (! function_exists('imagecreatetruecolor')) {
        $this->markTestSkipped('GD extension not available in test environment.');
    }

    Storage::fake('public');

    $store = Store::factory()->create([
        'background_color' => '#8B4513',
        'brand_color' => '#F4E6D8',
        'reward_target' => 8,
    ]);

    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'loyalty_program_id' => $store->resolvedDefaultProgram()->id,
        'stamp_count' => 3,
    ]);

    $renderer = app(GoogleWalletStampStripRenderer::class);
    $path = $renderer->generateForAccount($account);

    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});

test('changes stamp strip filename when progress or branding changes', function () {
    if (! function_exists('imagecreatetruecolor')) {
        $this->markTestSkipped('GD extension not available in test environment.');
    }

    Storage::fake('public');

    $store = Store::factory()->create([
        'background_color' => '#5A2E0A',
        'brand_color' => '#FFFFFF',
        'reward_target' => 8,
    ]);

    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'loyalty_program_id' => $store->resolvedDefaultProgram()->id,
        'stamp_count' => 2,
    ]);

    $renderer = app(GoogleWalletStampStripRenderer::class);
    $firstPath = $renderer->generateForAccount($account);

    $account->stamp_count = 4;
    $account->save();
    $secondPath = $renderer->generateForAccount($account->fresh());

    $program = $store->resolvedDefaultProgram();
    $program->background_color = '#1F2937';
    $program->save();
    $thirdPath = $renderer->generateForAccount($account->fresh());

    expect($firstPath)->not->toEqual($secondPath);
    expect($secondPath)->not->toEqual($thirdPath);
});

test('changes stamp strip filename when wallet card style changes', function () {
    if (! function_exists('imagecreatetruecolor')) {
        $this->markTestSkipped('GD extension not available in test environment.');
    }

    Storage::fake('public');

    $store = Store::factory()->create([
        'background_color' => '#123456',
        'brand_color' => '#F4E6D8',
        'reward_target' => 8,
        'wallet_card_style' => Store::WALLET_CARD_STYLE_CLASSIC,
    ]);

    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'loyalty_program_id' => $store->resolvedDefaultProgram()->id,
        'stamp_count' => 7,
    ]);

    $renderer = app(GoogleWalletStampStripRenderer::class);
    $classicPath = $renderer->generateForAccount($account);

    $store->forceFill(['wallet_card_style' => Store::WALLET_CARD_STYLE_ABSTRACT])->save();
    $abstractPath = $renderer->generateForAccount($account->fresh());

    expect($classicPath)->not->toBeNull()
        ->and($abstractPath)->not->toBeNull()
        ->and($classicPath)->not->toEqual($abstractPath);

    Storage::disk('public')->assertExists($abstractPath);
});

test('changes stamp strip filename when abstract pattern settings change', function () {
    if (! function_exists('imagecreatetruecolor')) {
        $this->markTestSkipped('GD extension not available in test environment.');
    }

    Storage::fake('public');

    $store = Store::factory()->create([
        'background_color' => '#2B1E18',
        'brand_color' => '#D6A24A',
        'reward_target' => 8,
        'wallet_card_style' => Store::WALLET_CARD_STYLE_ABSTRACT,
        'wallet_background_pattern' => Store::WALLET_BACKGROUND_PATTERN_DOTS,
        'wallet_pattern_color' => '#A7C7A1',
    ]);

    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'loyalty_program_id' => $store->resolvedDefaultProgram()->id,
        'stamp_count' => 4,
    ]);

    $renderer = app(GoogleWalletStampStripRenderer::class);
    $dotsPath = $renderer->generateForAccount($account);

    $store->forceFill([
        'wallet_background_pattern' => Store::WALLET_BACKGROUND_PATTERN_WAVES,
        'wallet_pattern_color' => '#C96A3B',
    ])->save();

    $wavesPath = $renderer->generateForAccount($account->fresh());

    expect($dotsPath)->not->toBeNull()
        ->and($wavesPath)->not->toBeNull()
        ->and($dotsPath)->not->toEqual($wavesPath);
});

test('abstract stamp strip can render an uploaded raster stamp icon', function () {
    if (! function_exists('imagecreatetruecolor')) {
        $this->markTestSkipped('GD extension not available in test environment.');
    }

    Storage::fake('public');
    $icon = imagecreatetruecolor(48, 48);
    imagefill($icon, 0, 0, imagecolorallocate($icon, 0, 0, 0));
    ob_start();
    imagepng($icon);
    $iconBinary = ob_get_clean();
    imagedestroy($icon);
    Storage::disk('public')->put('wallet-stamp-icons/test-icon.png', $iconBinary);

    $store = Store::factory()->create([
        'background_color' => '#2B1E18',
        'brand_color' => '#D6A24A',
        'reward_target' => 8,
        'wallet_card_style' => Store::WALLET_CARD_STYLE_ABSTRACT,
        'wallet_stamp_icon_path' => 'wallet-stamp-icons/test-icon.png',
    ]);

    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'loyalty_program_id' => $store->resolvedDefaultProgram()->id,
        'stamp_count' => 4,
    ]);

    $path = app(GoogleWalletStampStripRenderer::class)->generateForAccount($account);

    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});
