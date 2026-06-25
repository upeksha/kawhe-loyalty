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

test('join page shows branded invalid page with invalid token', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);

    $response = $this->get(route('join.show', ['slug' => $store->slug, 't' => 'invalid-token']));

    $response->assertNotFound();
    $response->assertViewIs('join.invalid');
    $response->assertSee('This link isn’t valid');
    $response->assertSee($store->name);
});

test('join page shows branded invalid page with invalid slug', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);

    $response = $this->get(route('join.show', ['slug' => 'invalid-slug', 't' => $store->join_token]));

    $response->assertNotFound();
    $response->assertViewIs('join.invalid');
    $response->assertSee('This link isn’t valid');
});

test('short join code shows branded invalid page when code is unknown', function () {
    $response = $this->get(route('join.short', ['code' => 'ZZZZZZ']));

    $response->assertNotFound();
    $response->assertViewIs('join.invalid');
    $response->assertSee('This link isn’t valid');
});
