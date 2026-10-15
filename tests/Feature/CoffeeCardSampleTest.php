<?php

use App\Models\LoyaltyAccount;
use App\Support\CoffeeCardSample;
use App\Services\Wallet\GoogleWalletPassService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function coffeeSampleAccount(): LoyaltyAccount
{
    config(['app.env' => 'testing', 'app.url' => 'https://testing.kawhe.shop']);
    $account = LoyaltyAccount::factory()->create(['stamp_count' => 3, 'reward_balance' => 0]);
    $account->resolvedProgram()->update(['slug' => 'kawhe-illustrated-wallet-qa', 'reward_target' => 8]);
    return $account->fresh(['store', 'loyaltyProgram', 'customer']);
}

test('coffee artwork is restricted to the dedicated testing program and target', function () {
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
    expect(CoffeeCardSample::enabled($account))->toBeFalse();
});

test('all progress and reward images exist at the platform dimensions', function () {
    $a = coffeeSampleAccount();
    foreach (['apple' => [1125,432], 'google' => [1032,812], 'web' => [720,470]] as $platform => $dimensions) {
        foreach (range(0,8) as $count) {
            $a->stamp_count = $count;
            $file = public_path(CoffeeCardSample::artwork($a, $platform));
            expect(array_slice(getimagesize($file), 0, 2))->toBe($dimensions);
        }
        $a->stamp_count = 0; $a->reward_balance = 1;
        expect(CoffeeCardSample::artwork($a,$platform))->toEndWith('-ready.png');
        expect(is_file(public_path(CoffeeCardSample::artwork($a,$platform))))->toBeTrue();
        $a->stamp_count = 2;
        expect(CoffeeCardSample::artwork($a,$platform))->toEndWith('-2.png');
        $a->stamp_count = 0; $a->reward_balance = 0;
        expect(CoffeeCardSample::artwork($a,$platform))->toEndWith('-0.png');
    }
});

test('Google sample image changes with balance and retains accessible progress', function () {
    $a = coffeeSampleAccount();
    $service = (new ReflectionClass(GoogleWalletPassService::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod($service, 'buildStampStripImage');
    $initial = $method->invoke($service, $a);
    expect($initial->getSourceUri()->getUri())->toEndWith('google-3.png');
    $a->stamp_count = 0; $a->reward_balance = 1;
    $ready = $method->invoke($service,$a);
    expect($ready->getSourceUri()->getUri())->toEndWith('google-ready.png');
    expect($ready->getContentDescription()->getDefaultValue()->getValue())->toContain('1 rewards available');
});

test('customer sample keeps working card controls and artwork while normal cards stay unchanged', function () {
    $a = coffeeSampleAccount();
    $this->get('/c/'.$a->public_token)->assertOk()->assertSee('coffee-sample-art')->assertSee('web-3.png')->assertSee('stamp-qr-container');
    $a->loyaltyProgram->update(['slug' => 'ordinary-program']);
    $this->get('/c/'.$a->public_token)->assertOk()->assertDontSee('id="coffee-sample-art"', false);
});
