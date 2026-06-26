<?php

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;
use App\Services\Wallet\AppleWalletPassService;
use App\Services\Wallet\GoogleWalletPassService;
use Illuminate\Support\Facades\URL;

test('billing page loads for authenticated merchant', function () {
    $user = User::factory()->create();
    Store::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('billing.index'))
        ->assertOk()
        ->assertViewHasAll(['stats', 'subscription', 'stripePriceId', 'planState']);
});

test('billing checkout fails gracefully when stripe is not configured', function () {
    config([
        'cashier.key' => null,
        'cashier.secret' => null,
        'cashier.price_id' => null,
    ]);

    $user = User::factory()->create();
    Store::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->from(route('billing.index'))->post(route('billing.checkout'));

    $response->assertRedirect(route('billing.index'));
    $response->assertSessionHasErrors('error');
});

test('signed apple wallet download route returns pkpass response with mocked service', function () {
    $store = Store::factory()->create();
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'public_token' => 'walletappletoken1',
    ]);

    $appleMock = \Mockery::mock(AppleWalletPassService::class);
    $appleMock->shouldReceive('generatePass')
        ->once()
        ->andReturn("PK\x03\x04fake-pass");
    $this->app->instance(AppleWalletPassService::class, $appleMock);

    $googleMock = \Mockery::mock(GoogleWalletPassService::class);
    $this->app->instance(GoogleWalletPassService::class, $googleMock);

    $url = URL::signedRoute('wallet.apple.download', ['public_token' => $account->public_token]);

    $this->get($url)
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.apple.pkpass');
});

test('unsigned apple wallet download route is rejected', function () {
    $this->get(route('wallet.apple.download', ['public_token' => 'unsignedtoken'], absolute: false))
        ->assertForbidden();
});

test('signed google wallet save route redirects to generated save url with mocked service', function () {
    $store = Store::factory()->create();
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'public_token' => 'walletgoogletokn',
    ]);

    $appleMock = \Mockery::mock(AppleWalletPassService::class);
    $this->app->instance(AppleWalletPassService::class, $appleMock);

    $googleMock = \Mockery::mock(GoogleWalletPassService::class);
    $googleMock->shouldReceive('generateSaveLink')
        ->once()
        ->andReturn('https://pay.google.com/gp/v/save/test-pass');
    $this->app->instance(GoogleWalletPassService::class, $googleMock);

    $url = URL::signedRoute('wallet.google.save', ['public_token' => $account->public_token]);

    $this->get($url)
        ->assertRedirect('https://pay.google.com/gp/v/save/test-pass');
});

test('unsigned google wallet save route is rejected', function () {
    $this->get(route('wallet.google.save', ['public_token' => 'unsignedtoken'], absolute: false))
        ->assertForbidden();
});
