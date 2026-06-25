<?php

use App\Models\Store;
use App\Models\User;
use Illuminate\Http\UploadedFile;

test('new merchant registration redirects to onboarding', function () {
    $response = $this->post('/register', [
        'name' => 'New Merchant',
        'email' => 'merchant@test.com',
        'store_name' => 'Merchant Test Cafe',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertRedirect(route('merchant.onboarding.wizard.store-basics'));

    $user = User::where('email', 'merchant@test.com')->first();
    expect($user)->not->toBeNull();
    expect($user->stores()->count())->toBe(1);
    expect($user->stores()->first()->name)->toBe('Merchant Test Cafe');
    expect($user->stores()->first()->onboarding_step)->toBe(\App\Http\Controllers\MerchantOnboardingWizardController::STEP_STORE_BASICS);
});

test('merchant can complete onboarding and create first store', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->post(route('merchant.onboarding.wizard.store-basics.store'), [
        'name' => 'My First Café',
        'address' => '123 Main St',
        'reward_target' => 10,
        'reward_title' => 'Free Coffee',
    ]);
    $response->assertRedirect(route('merchant.onboarding.wizard.card-design'));

    $response = $this->post(route('merchant.onboarding.wizard.card-design.store'), [
        'brand_color' => '#123456',
        'background_color' => '#654321',
        'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        'pass_logo' => UploadedFile::fake()->image('pass-logo.png', 160, 50),
        'pass_hero_image' => UploadedFile::fake()->image('pass-hero.png', 640, 180),
    ]);
    $response->assertRedirect(route('merchant.onboarding.wizard.customer-form'));

    $response = $this->post(route('merchant.onboarding.wizard.customer-form.store'), []);
    $response->assertRedirect(route('merchant.onboarding.wizard.card-ready'));

    $store = $user->stores()->first();
    expect($store)->not->toBeNull();
    expect($store->name)->toBe('My First Café');

    $response = $this->post(route('merchant.onboarding.wizard.complete'));
    $response->assertRedirect(route('merchant.stores.qr', $store));

    expect($store->fresh()->onboarding_completed_at)->not->toBeNull();
});

test('legacy onboarding POST redirects to wizard', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->post(route('merchant.onboarding.store.store'), [
        'name' => 'Legacy Café',
        'reward_target' => 10,
        'reward_title' => 'Free Coffee',
    ]);

    $response->assertRedirect(route('merchant.onboarding.wizard.store-basics'));
    expect($user->stores()->count())->toBe(0);
});

test('merchant with store can access merchant dashboard', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $response = $this->get(route('merchant.dashboard'));
    $response->assertOk();
});

test('merchant without store is redirected to onboarding from dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->get(route('merchant.dashboard'));
    $response->assertRedirect(route('merchant.onboarding.wizard.store-basics'));
});

test('super admin can access admin dashboard', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);

    $this->actingAs($admin);

    $response = $this->get(route('admin.dashboard'));
    $response->assertOk();
});

test('regular user cannot access admin dashboard', function () {
    $user = User::factory()->create(['is_super_admin' => false]);

    $this->actingAs($user);

    $response = $this->get(route('admin.dashboard'));
    $response->assertStatus(403);
});

test('super admin can view all stores', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    Store::factory()->count(2)->create(['user_id' => $user1->id]);
    Store::factory()->count(3)->create(['user_id' => $user2->id]);

    $this->actingAs($admin);

    $response = $this->get(route('merchant.stores.index'));
    $response->assertOk();

    // Admin should see all 5 stores
    expect(Store::queryForUser($admin)->count())->toBe(5);
});

test('regular merchant can only view their own stores', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    Store::factory()->count(2)->create(['user_id' => $user1->id]);
    Store::factory()->count(3)->create(['user_id' => $user2->id]);

    $this->actingAs($user1);

    // User 1 should only see their 2 stores
    expect(Store::queryForUser($user1)->count())->toBe(2);
});

test('merchant cannot access another merchants store', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $store1 = Store::factory()->create(['user_id' => $user1->id]);
    $store2 = Store::factory()->create(['user_id' => $user2->id]);

    $this->actingAs($user1);

    // Try to access user2's store
    $response = $this->get(route('merchant.stores.edit', $store2));
    $response->assertStatus(404); // Should not find it via queryForUser
});

test('old routes redirect to new merchant routes', function () {
    $user = User::factory()->create();
    Store::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $response = $this->get('/dashboard');
    $response->assertRedirect(route('merchant.dashboard'));

    $response = $this->get('/stores');
    $response->assertRedirect(route('merchant.stores.index'));

    $response = $this->get('/scanner');
    $response->assertRedirect(route('merchant.scanner'));
});

test('existing customer join flow still works', function () {
    $store = Store::factory()->create();

    $response = $this->get(route('join.index', ['slug' => $store->slug, 't' => $store->join_token]));
    $response->assertOk();
});
