<?php

use App\Jobs\UpdateWalletPassJob;
use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\UploadedFile;
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
    $defaultProgram = $store->resolvedDefaultProgram();

    expect($store->brand_color)->toBe(Store::DEFAULT_BRAND_COLOR)
        ->and($store->background_color)->toBe(Store::DEFAULT_BACKGROUND_COLOR)
        ->and($store->wallet_card_style)->toBe(Store::WALLET_CARD_STYLE_CLASSIC)
        ->and($store->registration_form_config['email']['enabled'])->toBeTrue()
        ->and($store->onboarding_step)->toBe(Store::ONBOARDING_STEP_CARD_DESIGN)
        ->and($defaultProgram)->not->toBeNull()
        ->and($defaultProgram->slug)->toBe($store->slug)
        ->and($defaultProgram->reward_target)->toBe(8)
        ->and($defaultProgram->reward_title)->toBe('Free flat white');

    $this->actingAs($user)
        ->post(route('merchant.onboarding.wizard.card-design.store'), [
            'brand_color' => '#123456',
            'background_color' => '#654321',
            'wallet_card_style' => Store::WALLET_CARD_STYLE_ABSTRACT,
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
            'pass_logo' => UploadedFile::fake()->image('pass-logo.png', 160, 50),
            'pass_hero_image' => UploadedFile::fake()->image('pass-hero.png', 640, 180),
        ])
        ->assertRedirect(route('merchant.onboarding.wizard.customer-form'));

    $store->refresh();
    $defaultProgram->refresh();

    expect($store->brand_color)->toBe('#123456')
        ->and($store->background_color)->toBe('#654321')
        ->and($store->wallet_card_style)->toBe(Store::WALLET_CARD_STYLE_ABSTRACT)
        ->and($store->logo_path)->not->toBeNull()
        ->and($store->pass_logo_path)->not->toBeNull()
        ->and($store->pass_hero_image_path)->not->toBeNull()
        ->and($store->onboarding_step)->toBe(Store::ONBOARDING_STEP_CUSTOMER_FORM)
        ->and($defaultProgram->brand_color)->toBe('#123456')
        ->and($defaultProgram->background_color)->toBe('#654321')
        ->and($defaultProgram->logo_path)->not->toBeNull()
        ->and($defaultProgram->pass_logo_path)->not->toBeNull()
        ->and($defaultProgram->pass_hero_image_path)->not->toBeNull();

    $this->actingAs($user)
        ->post(route('merchant.onboarding.wizard.customer-form.store'), [
            'first_name_enabled' => '1',
            'first_name_required' => '1',
            'phone_enabled' => '1',
            'phone_required' => '0',
        ])
        ->assertRedirect(route('merchant.onboarding.wizard.card-ready'));

    $store->refresh();
    $defaultProgram->refresh();

    expect($store->registration_form_config['first_name']['enabled'])->toBeTrue()
        ->and($store->registration_form_config['first_name']['required'])->toBeTrue()
        ->and($store->registration_form_config['phone']['enabled'])->toBeTrue()
        ->and($store->registration_form_config['phone']['required'])->toBeFalse()
        ->and($store->onboarding_step)->toBe(Store::ONBOARDING_STEP_CARD_READY)
        ->and($defaultProgram->registration_form_config['first_name']['enabled'])->toBeTrue()
        ->and($defaultProgram->registration_form_config['phone']['enabled'])->toBeTrue();
});

test('onboarding card design requires store logo and wallet assets', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('merchant.onboarding.wizard.store-basics.store'), [
            'name' => 'Branding Test Cafe',
            'reward_target' => 8,
            'reward_title' => 'Free coffee',
        ]);

    $response = $this->actingAs($user)
        ->from(route('merchant.onboarding.wizard.card-design'))
        ->post(route('merchant.onboarding.wizard.card-design.store'), [
            'brand_color' => '#123456',
            'background_color' => '#654321',
        ]);

    $response->assertRedirect(route('merchant.onboarding.wizard.card-design'));
    $response->assertSessionHasErrors(['logo', 'pass_logo', 'pass_hero_image']);
});

test('create store requires branding assets and saves them on the default card', function () {
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
        ->from(route('merchant.stores.create'))
        ->post(route('merchant.stores.store'), [
            'name' => 'Second Store',
            'address' => '22 Market Street',
            'reward_target' => 9,
            'reward_title' => 'Free pastry',
            'brand_color' => '#123456',
            'background_color' => '#654321',
        ])
        ->assertRedirect(route('merchant.stores.create'))
        ->assertSessionHasErrors(['logo', 'pass_logo', 'pass_hero_image']);

    $this->actingAs($user)
        ->post(route('merchant.stores.store'), [
            'name' => 'Second Store',
            'address' => '22 Market Street',
            'reward_target' => 9,
            'reward_title' => 'Free pastry',
            'brand_color' => '#123456',
            'background_color' => '#654321',
            'wallet_card_style' => Store::WALLET_CARD_STYLE_ABSTRACT,
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
            'pass_logo' => UploadedFile::fake()->image('pass-logo.png', 160, 50),
            'pass_hero_image' => UploadedFile::fake()->image('pass-hero.png', 640, 180),
        ])
        ->assertRedirect(route('merchant.stores.index'));

    $store = Store::where('user_id', $user->id)
        ->where('name', 'Second Store')
        ->firstOrFail();
    $program = $store->resolvedDefaultProgram();

    expect($store->brand_color)->toBe('#123456')
        ->and($store->background_color)->toBe('#654321')
        ->and($store->wallet_card_style)->toBe(Store::WALLET_CARD_STYLE_ABSTRACT)
        ->and($store->logo_path)->not->toBeNull()
        ->and($store->pass_logo_path)->not->toBeNull()
        ->and($store->pass_hero_image_path)->not->toBeNull()
        ->and($program)->not->toBeNull()
        ->and($program->logo_path)->not->toBeNull()
        ->and($program->pass_logo_path)->not->toBeNull()
        ->and($program->pass_hero_image_path)->not->toBeNull();
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

test('short join link self-heals legacy store without a default loyalty program', function () {
    $user = User::factory()->create();

    $store = $user->stores()->create([
        'name' => 'Legacy Join Store',
        'reward_target' => 9,
        'reward_title' => 'Free coffee',
        'join_token' => 'legacy-store-join-token-1234567890',
        'join_short_code' => 'W37Y3V',
    ]);

    expect($store->default_loyalty_program_id)->toBeNull()
        ->and($store->loyaltyPrograms()->count())->toBe(0);

    $response = $this->get(route('join.short', ['code' => $store->join_short_code]));

    $store->refresh();
    $program = $store->resolvedDefaultProgram();

    $response->assertRedirect(route('join.index', [
        'slug' => $program->slug,
        't' => $program->join_token,
    ]));

    expect($program)->not->toBeNull()
        ->and($store->default_loyalty_program_id)->toBe($program->id)
        ->and($program->slug)->toBe($store->slug);
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
