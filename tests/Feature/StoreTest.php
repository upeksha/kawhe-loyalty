<?php

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Laravel\Cashier\Subscription;

function validStoreCreatePayload(array $overrides = []): array
{
    return array_merge([
        'reward_target' => 9,
        'reward_title' => 'Free Coffee',
        'brand_color' => '#123456',
        'background_color' => '#654321',
        'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        'pass_logo' => UploadedFile::fake()->image('pass-logo.png', 160, 50),
        'pass_hero_image' => UploadedFile::fake()->image('pass-hero.png', 640, 180),
    ], $overrides);
}

test('authenticated merchant with an existing store can create another store', function () {
    $user = User::factory()->create(['stripe_id' => 'sub_123']);
    Store::factory()->create(['user_id' => $user->id]);
    Subscription::create([
        'user_id' => $user->id,
        'name' => 'default',
        'stripe_id' => 'si_create_store_123',
        'stripe_status' => 'active',
        'quantity' => 1,
    ]);

    $response = $this->actingAs($user)->post('/merchant/stores', validStoreCreatePayload([
        'name' => 'My Awesome Coffee Shop',
        'address' => '123 Main St',
    ]));

    $response->assertRedirect('/merchant/stores');
    $this->assertDatabaseHas('stores', [
        'name' => 'My Awesome Coffee Shop',
        'user_id' => $user->id,
    ]);

    $store = Store::where('name', 'My Awesome Coffee Shop')->first();
    expect($store->slug)->not->toBeNull();
    expect($store->join_token)->not->toBeNull();
});

test('merchant without a store is redirected to the setup wizard from stores index', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('merchant.stores.index'));

    $response->assertRedirect(route('merchant.onboarding.wizard.store-basics'));
});

test('merchant with onboarding in progress is redirected back to the wizard from stores index', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create([
        'user_id' => $user->id,
        'onboarding_step' => Store::ONBOARDING_STEP_CARD_DESIGN,
        'onboarding_completed_at' => null,
    ]);

    $response = $this->actingAs($user)->get(route('merchant.stores.index'));

    $response->assertRedirect(route('merchant.onboarding.wizard.index'));
});

test('slug is unique', function () {
    $user = User::factory()->create(['stripe_id' => 'sub_123']);
    Store::factory()->create(['user_id' => $user->id]);
    Subscription::create([
        'user_id' => $user->id,
        'name' => 'default',
        'stripe_id' => 'si_123',
        'stripe_status' => 'active',
        'quantity' => 1,
    ]);

    $this->actingAs($user)->post('/merchant/stores', validStoreCreatePayload([
        'name' => 'Coffee Shop',
    ]));

    $this->actingAs($user)->post('/merchant/stores', validStoreCreatePayload([
        'name' => 'Coffee Shop',
    ]));

    $stores = Store::where('name', 'Coffee Shop')->get();
    expect($stores)->toHaveCount(2);
    expect($stores[0]->slug)->not->toBe($stores[1]->slug);
});

test('user cannot view another users store qr', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $owner->id]);

    $otherUser = User::factory()->create();
    Store::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->actingAs($otherUser)->get(route('merchant.stores.qr', $store));

    $response->assertForbidden();
});

test('owner can view their store qr', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($owner)->get(route('merchant.stores.qr', $store));

    $response->assertOk();
    $response->assertViewHas('joinUrl');
});

test('owner can download their store qr as svg', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $owner->id, 'name' => 'My Coffee Shop']);

    $response = $this->actingAs($owner)->get(route('merchant.stores.qr.image', $store));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/svg+xml');
    $response->assertHeader('Content-Disposition', 'attachment; filename=my-coffee-shop-qr-code.svg');
});

test('merchant can update store details without card settings in the payload', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->create([
        'user_id' => $owner->id,
        'reward_target' => 9,
        'reward_title' => 'Free Coffee',
        'require_verification_for_redemption' => true,
    ]);

    $response = $this->actingAs($owner)->put(route('merchant.stores.update', $store), [
        'name' => 'Updated Store Name',
        'address' => '99 Harbour Street',
        'brand_color' => '#111827',
        'background_color' => '#F5F5F4',
        'wallet_card_style' => Store::WALLET_CARD_STYLE_ABSTRACT,
    ]);

    $response->assertRedirect(route('merchant.stores.index'));

    $updated = $store->fresh();
    expect($updated->name)->toBe('Updated Store Name');
    expect($updated->address)->toBe('99 Harbour Street');
    expect($updated->brand_color)->toBe('#111827');
    expect($updated->background_color)->toBe('#F5F5F4');
    expect($updated->wallet_card_style)->toBe(Store::WALLET_CARD_STYLE_ABSTRACT);
    expect($updated->reward_target)->toBe(9);
    expect($updated->reward_title)->toBe('Free Coffee');
    expect((bool) $updated->require_verification_for_redemption)->toBeTrue();
});

test('merchant can still update other store details after customers have joined', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->create([
        'user_id' => $owner->id,
        'reward_target' => 9,
        'brand_color' => '#0EA5E9',
        'background_color' => '#1F2937',
    ]);
    $customer = Customer::factory()->create();

    LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
    ]);

    $response = $this->actingAs($owner)->put(route('merchant.stores.update', $store), [
        'name' => 'Updated Store Name',
        'address' => '99 Harbour Street',
        'brand_color' => '#111827',
        'background_color' => '#F5F5F4',
    ]);

    $response->assertRedirect(route('merchant.stores.index'));

    $updated = $store->fresh();
    expect($updated->name)->toBe('Updated Store Name');
    expect($updated->address)->toBe('99 Harbour Street');
    expect($updated->brand_color)->toBe('#111827');
    expect($updated->background_color)->toBe('#F5F5F4');
    expect($updated->reward_target)->toBe(9);
});

test('free merchant cannot create a second store because it would add another default card', function () {
    $owner = User::factory()->create();
    Store::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($owner)->post('/merchant/stores', [
        'name' => 'Second Store',
        'address' => '2 Queen Street',
        'reward_target' => 9,
        'reward_title' => 'Free Coffee',
    ]);

    $response->assertRedirect(route('billing.index'));
    $response->assertSessionHasErrors('error');
    expect(Store::where('user_id', $owner->id)->count())->toBe(1);
});

test('free merchant at store limit does not see add another store button', function () {
    $owner = User::factory()->create();
    Store::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($owner)->get(route('merchant.stores.index'));

    $response->assertOk();
    $response->assertDontSee('Add Another Store', false);
});

test('free merchant at store limit is redirected from create store form', function () {
    $owner = User::factory()->create();
    Store::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($owner)->get(route('merchant.stores.create'));

    $response->assertRedirect(route('merchant.stores.index'));
});
