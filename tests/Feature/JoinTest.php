<?php

use App\Models\Store;
use App\Models\User;

test('join page works with valid token', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);

    $response = $this->get(route('join.show', ['slug' => $store->slug, 't' => $store->join_token]));

    $response->assertOk();
    $response->assertSee($store->name);
});

test('join page returns 404 with invalid token', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);

    $response = $this->get(route('join.show', ['slug' => $store->slug, 't' => 'invalid-token']));

    $response->assertNotFound();
});

test('join page returns 404 with invalid slug', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);

    $response = $this->get(route('join.show', ['slug' => 'invalid-slug', 't' => $store->join_token]));

    $response->assertNotFound();
});
