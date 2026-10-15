<?php

use App\Models\LoyaltyAccount;
use App\Services\Wallet\GoogleWalletPassService;
use App\Support\CoffeeCardSample;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function coffeeSampleAccount(): LoyaltyAccount
{
    config(['app.env' => 'testing', 'app.url' => 'https://testing.kawhe.shop']);
    $account = LoyaltyAccount::factory()->create(['stamp_count' => 3, 'reward_balance' => 0]);
    $account->resolvedProgram()->update(['slug' => 'kawhe-illustrated-wallet-qa', 'reward_target' => 8]);

    return $account->fresh(['store', 'loyaltyProgram', 'customer']);
}

test('coffee artwork is restricted to the dedicated testing program', function () {
    $account = coffeeSampleAccount();
    expect(CoffeeCardSample::enabled($account))->toBeTrue();
    config(['app.env' => 'production']);
    expect(CoffeeCardSample::enabled($account))->toBeFalse();
    config(['app.env' => 'testing', 'app.url' => 'https://app.kawhe.shop']);
    expect(CoffeeCardSample::enabled($account))->toBeFalse();
    config(['app.url' => 'https://testing.kawhe.shop']);
    $account->loyaltyProgram->slug = 'another-program';
    expect(CoffeeCardSample::enabled($account))->toBeFalse();
    $account->loyaltyProgram->slug = 'kawhe-illustrated-wallet-qa';
    $account->loyaltyProgram->reward_target = 10;
    expect(CoffeeCardSample::enabled($account))->toBeTrue();
});

test('all progress and reward images exist at the platform dimensions', function () {
    $a = coffeeSampleAccount();
    foreach (['apple' => [1125, 432], 'google' => [1032, 812], 'web' => [720, 470]] as $platform => $dimensions) {
        foreach (range(0, 8) as $count) {
            $a->stamp_count = $count;
            $contents = \App\Support\StoreAssets::get(CoffeeCardSample::artwork($a, $platform));
            expect(array_slice(getimagesizefromstring($contents), 0, 2))->toBe($dimensions);
        }
        $a->stamp_count = 0;
        $a->reward_balance = 1;
        expect(CoffeeCardSample::artwork($a, $platform))->toEndWith('-ready.png');
        expect(\App\Support\StoreAssets::exists(CoffeeCardSample::artwork($a, $platform)))->toBeTrue();
        $a->stamp_count = 2;
        expect(CoffeeCardSample::artwork($a, $platform))->toEndWith('-2.png');
        $a->stamp_count = 0;
        $a->reward_balance = 0;
        expect(CoffeeCardSample::artwork($a, $platform))->toEndWith('-0.png');
    }
});

test('Google sample image changes with balance and retains accessible progress', function () {
    $a = coffeeSampleAccount();
    $service = (new ReflectionClass(GoogleWalletPassService::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod($service, 'buildStampStripImage');
    $initial = $method->invoke($service, $a);
    expect($initial->getSourceUri()->getUri())->toEndWith('google-8-3.png');
    $a->stamp_count = 0;
    $a->reward_balance = 1;
    $ready = $method->invoke($service, $a);
    expect($ready->getSourceUri()->getUri())->toEndWith('google-8-ready.png');
    expect($ready->getContentDescription()->getDefaultValue()->getValue())->toContain('1 rewards available');
});

test('customer sample keeps working card controls and artwork while normal cards stay unchanged', function () {
    $a = coffeeSampleAccount();
    $this->get('/c/'.$a->public_token)->assertOk()->assertSee('coffee-sample-art')->assertSee('web-8-3.png')->assertSee('stamp-qr-container');
    $a->loyaltyProgram->update(['slug' => 'ordinary-program']);
    $this->get('/c/'.$a->public_token)->assertOk()->assertDontSee('id="coffee-sample-art"', false);
});

test('Apple sample logos preserve transparent backgrounds at every resolution', function () {
    foreach ([160, 320, 480] as $width) {
        $png = CoffeeCardSample::resizedPng(public_path('images/coffee-sample-v1/logo.png'), $width);
        $image = imagecreatefromstring($png);
        expect(imagesx($image))->toBe($width);
        expect(imagecolorsforindex($image, imagecolorat($image, 0, 0))['alpha'])->toBe(127);
        $hasInk = false;
        for ($y = 0; $y < imagesy($image); $y++) {
            for ($x = 0; $x < imagesx($image); $x++) {
                if (imagecolorsforindex($image, imagecolorat($image, $x, $y))['alpha'] === 0) {
                    $hasInk = true;
                    break 2;
                }
            }
        }
        expect($hasInk)->toBeTrue();
        imagedestroy($image);
    }
});

test('adaptive artwork supports small odd and large targets without oversized images', function () {
    $renderer = app(\App\Services\Wallet\Artwork\CoffeeStampArtwork::class);
    foreach ([1, 3, 6, 8, 9, 10, 12, 16, 20, 21, 50, 1000] as $target) {
        foreach (['apple' => [1125, 432], 'google' => [1032, 812], 'web' => [720, 470]] as $platform => $dimensions) {
            $empty = $renderer->render($target, 0, false, $platform);
            $filled = $renderer->render($target, $target, false, $platform);
            expect(array_slice(getimagesizefromstring($filled), 0, 2))->toBe($dimensions);
            expect(hash('sha256', $filled))->not->toBe(hash('sha256', $empty));
            expect(strlen($filled))->toBeLessThan(500000);
        }
    }
});

test('long unicode reward labels are bounded without losing stored reward text', function () {
    foreach (['Free regular coffee', str_repeat('W', 100), '  Free   coffee with a freshly baked pastry and extra toppings  ', str_repeat('☕咖啡', 30)] as $title) {
        $short = CoffeeCardSample::shortReward($title);
        expect(mb_strlen($short))->toBeLessThanOrEqual(24);
        expect(mb_check_encoding($short, 'UTF-8'))->toBeTrue();
    }
    $a = coffeeSampleAccount();
    $title = 'Free large coffee with a freshly baked pastry and your choice of extra toppings';
    $a->loyaltyProgram->update(['reward_title' => $title, 'reward_target' => 21]);
    $this->get('/c/'.$a->public_token)->assertOk()->assertSee(strtolower($title))->assertDontSee('id="stamp-circles-container"', false);
    $response = $this->getJson('/api/card/'.$a->public_token)->assertOk()->assertJsonPath('reward_title', $title)->assertJsonPath('reward_target', 21);
    expect($response->json('coffee_artwork_url'))->toContain('web-21-3.png');
    $a->update(['stamp_count' => 0, 'reward_balance' => 2]);
    $response = $this->getJson('/api/card/'.$a->public_token)->assertOk();
    expect($response->json('coffee_artwork_url'))->toContain('web-21-ready.png');
    $a->update(['stamp_count' => 5]);
    expect($this->getJson('/api/card/'.$a->public_token)->json('coffee_artwork_url'))->toContain('web-21-5.png');
});
