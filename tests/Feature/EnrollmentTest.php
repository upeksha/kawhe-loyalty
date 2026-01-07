<?php

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;

test('enrollment requires email or phone', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);

    $response = $this->post(route('join.store', ['slug' => $store->slug, 't' => $store->join_token]), [
        'name' => 'John Doe',
        // No email or phone
    ]);

    $response->assertSessionHasErrors(['email']);
});

test('enrollment creates customer and loyalty account', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);

    $response = $this->post(route('join.store', ['slug' => $store->slug, 't' => $store->join_token]), [
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $customer = Customer::where('email', 'john@example.com')->first();
    $this->assertNotNull($customer);
    $this->assertEquals('John Doe', $customer->name);

    $this->assertDatabaseHas('loyalty_accounts', [
        'store_id' => $store->id,
        'customer_id' => $customer->id,
    ]);

    $account = LoyaltyAccount::where('customer_id', $customer->id)->first();
    $response->assertRedirect(route('card.show', ['public_token' => $account->public_token]));
});

test('enrollment reuses existing customer', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::create(['name' => 'Existing User', 'email' => 'existing@example.com']);

    $response = $this->post(route('join.store', ['slug' => $store->slug, 't' => $store->join_token]), [
        'name' => 'New Name',
        'email' => 'existing@example.com',
    ]);

    $this->assertEquals(1, Customer::count());
    $customer->refresh();
    $this->assertEquals('New Name', $customer->name); // Name should update

    $this->assertDatabaseHas('loyalty_accounts', [
        'store_id' => $store->id,
        'customer_id' => $customer->id,
    ]);
});

test('enrollment reuses existing loyalty account', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::create(['email' => 'test@example.com']);
    $account = LoyaltyAccount::create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
    ]);

    $response = $this->post(route('join.store', ['slug' => $store->slug, 't' => $store->join_token]), [
        'email' => 'test@example.com',
    ]);

    $this->assertEquals(1, LoyaltyAccount::count());
    $response->assertRedirect(route('card.show', ['public_token' => $account->public_token]));
});

test('card page displays correct info', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id, 'reward_title' => 'Free Cake']);
    $customer = Customer::create(['name' => 'Jane Doe']);
    $account = LoyaltyAccount::create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'stamp_count' => 5,
    ]);

    $response = $this->get(route('card.show', ['public_token' => $account->public_token]));

    $response->assertOk();
    $response->assertSee($store->name);
    $response->assertSee('Free Cake');
    $response->assertSee('5 / 9'); // 9 is default target
    $response->assertSee('Jane Doe');
});
