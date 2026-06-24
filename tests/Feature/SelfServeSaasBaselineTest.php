<?php

use App\Jobs\UpdateWalletPassJob;
use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Cashier\Subscription;

test('onboarding wizard can create and advance a first store with safe defaults', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('merchant.onboarding.wizard.store-basics.store'), [
            'name' => 'North Wharf Coffee',
            'address' => '123 Harbour Road',
            'reward_target' => 8,
            'reward_title' => 'Free flat white',
        ])
        ->assertRedirect(route('merchant.onboarding.wizard.card-design'));

    $store = $user->stores()->firstOrFail();

    expect($store->brand_color)->toBe(Store::DEFAULT_BRAND_COLOR)
        ->and($store->background_color)->toBe(Store::DEFAULT_BACKGROUND_COLOR)
        ->and($store->registration_form_config['email']['enabled'])->toBeTrue()
        ->and($store->onboarding_step)->toBe(Store::ONBOARDING_STEP_CARD_DESIGN);

    $this->actingAs($user)
        ->post(route('merchant.onboarding.wizard.card-design.store'), [
            'brand_color' => '#123456',
            'background_color' => '#654321',
        ])
        ->assertRedirect(route('merchant.onboarding.wizard.customer-form'));

    $store->refresh();

    expect($store->brand_color)->toBe('#123456')
        ->and($store->background_color)->toBe('#654321')
        ->and($store->onboarding_step)->toBe(Store::ONBOARDING_STEP_CUSTOMER_FORM);

    $this->actingAs($user)
        ->post(route('merchant.onboarding.wizard.customer-form.store'), [
            'first_name_enabled' => '1',
            'first_name_required' => '1',
            'phone_enabled' => '1',
            'phone_required' => '0',
        ])
        ->assertRedirect(route('merchant.onboarding.wizard.card-ready'));

    $store->refresh();

    expect($store->registration_form_config['first_name']['enabled'])->toBeTrue()
        ->and($store->registration_form_config['first_name']['required'])->toBeTrue()
        ->and($store->registration_form_config['phone']['enabled'])->toBeTrue()
        ->and($store->registration_form_config['phone']['required'])->toBeFalse()
        ->and($store->onboarding_step)->toBe(Store::ONBOARDING_STEP_CARD_READY);
});

test('create store applies safe brand defaults when merchant skips branding fields', function () {
    $user = User::factory()->create(['stripe_id' => 'sub_123']);
    Store::factory()->create(['user_id' => $user->id]);
    Subscription::create([
        'user_id' => $user->id,
        'name' => 'default',
        'stripe_id' => 'si_123',
        'stripe_status' => 'active',
        'quantity' => 1,
    ]);

    $this->actingAs($user)
        ->post(route('merchant.stores.store'), [
            'name' => 'Second Store',
            'address' => '22 Market Street',
            'reward_target' => 9,
            'reward_title' => 'Free pastry',
        ])
        ->assertRedirect(route('merchant.stores.index'));

    $store = Store::where('user_id', $user->id)
        ->where('name', 'Second Store')
        ->firstOrFail();

    expect($store->brand_color)->toBe(Store::DEFAULT_BRAND_COLOR)
        ->and($store->background_color)->toBe(Store::DEFAULT_BACKGROUND_COLOR)
        ->and($store->registration_form_config['email']['required'])->toBeTrue();
});

test('customer can join and repeated join by same email returns existing card', function () {
    $store = Store::factory()->create([
        'registration_form_config' => Store::defaultRegistrationFormConfig(),
    ]);

    $joinPayload = [
        'email' => 'john@example.com',
        'first_name' => 'John',
    ];

    $firstResponse = $this->post(route('join.store', ['slug' => $store->slug, 't' => $store->join_token]), $joinPayload);
    $firstResponse->assertRedirect();

    $account = LoyaltyAccount::with('customer')->firstOrFail();

    expect($account->customer->email)->toBe('john@example.com')
        ->and($account->store_id)->toBe($store->id);

    $secondResponse = $this->post(route('join.store', ['slug' => $store->slug, 't' => $store->join_token]), $joinPayload);
    $secondResponse->assertRedirect(route('card.show', ['public_token' => $account->public_token]));

    expect(LoyaltyAccount::count())->toBe(1)
        ->and(Customer::count())->toBe(1);
});

test('stamp route earns reward and dispatches wallet update job', function () {
    Queue::fake();

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $store = Store::factory()->create(['user_id' => $user->id, 'reward_target' => 5]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'stamp_count' => 4,
        'reward_balance' => 0,
    ]);

    $this->actingAs($user)
        ->postJson(route('stamp.store'), [
            'store_id' => $store->id,
            'token' => $account->public_token,
            'idempotency_key' => 'baseline-stamp-key',
        ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'rewardEarned' => true,
        ]);

    $account->refresh();

    expect($account->stamp_count)->toBe(0)
        ->and($account->reward_balance)->toBe(1)
        ->and($account->redeem_token)->not->toBeNull();

    Queue::assertPushed(UpdateWalletPassJob::class, fn (UpdateWalletPassJob $job) => $job->loyaltyAccountId === $account->id);
});

test('redeem route blocks unverified users and succeeds once verified', function () {
    Queue::fake();

    $user = User::factory()->create();
    $store = Store::factory()->create([
        'user_id' => $user->id,
        'reward_target' => 5,
        'require_verification_for_redemption' => true,
    ]);
    $customer = Customer::factory()->create([
        'email' => 'redeem@example.com',
    ]);
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'stamp_count' => 2,
        'reward_balance' => 1,
        'reward_available_at' => now(),
        'redeem_token' => 'redeemtoken1234',
        'verified_at' => null,
    ]);

    $this->actingAs($user)
        ->postJson(route('redeem.store'), [
            'store_id' => $store->id,
            'token' => 'REDEEM:redeemtoken1234',
        ])
        ->assertStatus(422)
        ->assertJson([
            'status' => 'verification_required',
            'success' => false,
        ]);

    $account->forceFill(['verified_at' => now()])->save();

    $this->actingAs($user)
        ->postJson(route('redeem.store'), [
            'store_id' => $store->id,
            'token' => 'REDEEM:redeemtoken1234',
            'idempotency_key' => 'baseline-redeem-key',
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    $account->refresh();

    expect($account->reward_balance)->toBe(0)
        ->and($account->stamp_count)->toBe(2)
        ->and($account->redeem_token)->toBeNull();

    Queue::assertPushed(UpdateWalletPassJob::class, fn (UpdateWalletPassJob $job) => $job->loyaltyAccountId === $account->id);
});
