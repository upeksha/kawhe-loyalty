<?php

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;
use Laravel\Cashier\Subscription;

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

    $response = $this->actingAs($user)->post('/merchant/stores', [
        'name' => 'My Awesome Coffee Shop',
        'address' => '123 Main St',
        'reward_target' => 9,
        'reward_title' => 'Free Coffee',
    ]);

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

    $this->actingAs($user)->post('/merchant/stores', [
        'name' => 'Coffee Shop',
        'reward_target' => 9,
        'reward_title' => 'Free Coffee',
    ]);

    $this->actingAs($user)->post('/merchant/stores', [
        'name' => 'Coffee Shop',
        'reward_target' => 9,
        'reward_title' => 'Free Coffee',
    ]);

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

test('merchant can change reward target before any customers join', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->create([
        'user_id' => $owner->id,
        'reward_target' => 9,
    ]);

    $response = $this->actingAs($owner)->put(route('merchant.stores.update', $store), [
        'name' => $store->name,
        'address' => $store->address,
        'reward_target' => 12,
        'reward_title' => $store->reward_title,
    ]);

    $response->assertRedirect(route('merchant.stores.index'));
    expect($store->fresh()->reward_target)->toBe(12);
});

test('merchant cannot change reward target after customers have joined', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->create([
        'user_id' => $owner->id,
        'reward_target' => 9,
    ]);
    $customer = Customer::factory()->create();

    LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
    ]);

    $response = $this->from(route('merchant.stores.edit', $store))
        ->actingAs($owner)
        ->put(route('merchant.stores.update', $store), [
            'name' => $store->name,
            'address' => $store->address,
            'reward_target' => 12,
            'reward_title' => $store->reward_title,
        ]);

    $response->assertRedirect(route('merchant.stores.edit', $store));
    $response->assertSessionHasErrors('reward_target');
    expect($store->fresh()->reward_target)->toBe(9);
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
        'reward_title' => $store->reward_title,
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
