<?php

use App\Models\User;
use App\Models\Store;

test('new merchant registration redirects to onboarding', function () {
    $response = $this->post('/register', [
        'name' => 'New Merchant',
        'email' => 'merchant@test.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertRedirect(route('merchant.onboarding.store'));
    
    $user = User::where('email', 'merchant@test.com')->first();
    expect($user)->not->toBeNull();
    expect($user->stores()->count())->toBe(0);
});

test('merchant can complete onboarding and create first store', function () {
    $user = User::factory()->create();
    
    $this->actingAs($user);
    
    $response = $this->post(route('merchant.onboarding.store.store'), [
        'name' => 'My First Café',
        'address' => '123 Main St',
        'reward_target' => 10,
        'reward_title' => 'Free Coffee',
    ]);

    expect($user->stores()->count())->toBe(1);
    
    $store = $user->stores()->first();
    $response->assertRedirect(route('merchant.stores.qr', $store));
});

test('merchant with store can access merchant dashboard', function () {
    $user = User::factory()->create();
    Store::factory()->create(['user_id' => $user->id]);
    
    $this->actingAs($user);
    
    $response = $this->get(route('merchant.dashboard'));
    $response->assertRedirect('/merchant');
    $response = $this->get('/merchant');
    $response->assertOk();
});

test('merchant without store is redirected to onboarding from dashboard', function () {
    $user = User::factory()->create();
    
    $this->actingAs($user);
    
    $response = $this->get(route('merchant.dashboard'));
    $response->assertRedirect(route('merchant.onboarding.store'));
});

test('super admin can access admin dashboard', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    
    $this->actingAs($admin);
    
    $response = $this->get(route('admin.dashboard'));
    $response->assertRedirect('/admin');
    $response = $this->get('/admin');
    $response->assertOk();
});

test('regular user cannot access admin dashboard', function () {
    $user = User::factory()->create(['is_super_admin' => false]);
    
    $this->actingAs($user);
    
    $response = $this->get('/admin');
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
    $response->assertRedirect('/merchant/stores');
    $response = $this->get('/merchant/stores');
    $response->assertOk();
    
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
    
    Store::factory()->create(['user_id' => $user1->id]);
    $store2 = Store::factory()->create(['user_id' => $user2->id]);
    
    $this->actingAs($user1);
    
    $response = $this->get('/merchant/stores/'.$store2->id.'/edit');
    // Filament resolves record from scoped query; other user's store not found (404 or 403)
    expect([403, 404])->toContain($response->status());
});

test('old routes redirect to new merchant routes', function () {
    $user = User::factory()->create();
    Store::factory()->create(['user_id' => $user->id]);
    
    $this->actingAs($user);
    
    $response = $this->get('/dashboard');
    $response->assertRedirect('/merchant');
    
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
