<?php

use App\Models\Store;
use App\Models\User;

test('authenticated user can create a store', function () {
    $user = User::factory()->create();

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

test('slug is unique', function () {
    $user = User::factory()->create();

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
