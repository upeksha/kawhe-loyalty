<?php

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('join is blocked when free plan customer cap is reached', function () {
    $user = User::factory()->create();
    $store = Store::factory()->for($user)->create();
    $program = $store->loyaltyPrograms()->first();

    for ($i = 0; $i < 100; $i++) {
        LoyaltyAccount::create([
            'store_id' => $store->id,
            'loyalty_program_id' => $program->id,
            'customer_id' => Customer::factory()->create()->id,
        ]);
    }

    $response = $this->post(route('join.store', [
        'slug' => $program->slug,
        't' => $program->join_token,
    ]), [
        'email' => 'newcustomer@example.com',
        'name' => 'New Customer',
    ]);

    $response->assertOk();
    $response->assertSee('Limit Reached');
    $response->assertSee('not accepting new loyalty cards');
    expect(LoyaltyAccount::where('loyalty_program_id', $program->id)->count())->toBe(100);
});

test('existing customer can still access card when free plan customer cap is reached', function () {
    $user = User::factory()->create();
    $store = Store::factory()->for($user)->create();
    $program = $store->loyaltyPrograms()->first();
    $customer = Customer::factory()->create(['email' => 'returning@example.com']);

    for ($i = 0; $i < 99; $i++) {
        LoyaltyAccount::create([
            'store_id' => $store->id,
            'loyalty_program_id' => $program->id,
            'customer_id' => Customer::factory()->create()->id,
        ]);
    }

    $existing = LoyaltyAccount::create([
        'store_id' => $store->id,
        'loyalty_program_id' => $program->id,
        'customer_id' => $customer->id,
    ]);

    LoyaltyAccount::create([
        'store_id' => $store->id,
        'loyalty_program_id' => $program->id,
        'customer_id' => Customer::factory()->create()->id,
    ]);

    $response = $this->post(route('join.store', [
        'slug' => $program->slug,
        't' => $program->join_token,
    ]), [
        'email' => 'returning@example.com',
        'name' => 'Returning Customer',
    ]);

    $response->assertRedirect(route('card.show', ['public_token' => $existing->public_token]));
});
