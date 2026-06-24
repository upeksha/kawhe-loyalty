<?php

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyProgram;
use App\Models\Store;
use App\Models\User;
use Laravel\Cashier\Subscription;

test('merchant can create an additional loyalty program for a store', function () {
    $owner = User::factory()->create(['stripe_id' => 'sub_123']);
    $store = Store::factory()->create(['user_id' => $owner->id]);
    Subscription::create([
        'user_id' => $owner->id,
        'name' => 'default',
        'stripe_id' => 'si_123',
        'stripe_status' => 'active',
        'quantity' => 1,
    ]);

    $response = $this->actingAs($owner)->post(route('merchant.stores.programs.store', $store), [
        'name' => 'Iced Drinks Card',
        'reward_target' => 6,
        'reward_title' => 'Free iced coffee',
        'brand_color' => '#2563EB',
        'background_color' => '#0F172A',
        'require_verification_for_redemption' => '1',
        'first_name_enabled' => '1',
        'phone_enabled' => '1',
    ]);

    $program = LoyaltyProgram::where('store_id', $store->id)
        ->where('name', 'Iced Drinks Card')
        ->first();

    expect($program)->not->toBeNull();
    $response->assertRedirect(route('merchant.stores.programs.edit', [$store, $program]));
    expect($program->is_default)->toBeFalse();
    expect($program->reward_target)->toBe(6);
    expect($program->reward_title)->toBe('Free iced coffee');
    expect($program->registration_form_config['phone']['enabled'])->toBeTrue();
});

test('program-specific join creates loyalty account under the selected program', function () {
    $store = Store::factory()->create();
    $program = LoyaltyProgram::create([
        'store_id' => $store->id,
        'name' => 'Pastry Card',
        'reward_target' => 4,
        'reward_title' => 'Free pastry',
        'brand_color' => '#D97706',
        'background_color' => '#431407',
        'registration_form_config' => Store::defaultRegistrationFormConfig(),
        'sort_order' => 2,
    ]);

    $response = $this->post(route('join.store', ['slug' => $program->slug, 't' => $program->join_token]), [
        'name' => 'Program Customer',
        'email' => 'program@example.com',
    ]);

    $account = LoyaltyAccount::where('loyalty_program_id', $program->id)->first();

    $response->assertRedirect(route('card.show', ['public_token' => $account->public_token]));
    expect($account)->not->toBeNull();
    expect($account->store_id)->toBe($store->id);
    expect($account->loyalty_program_id)->toBe($program->id);
});

test('merchant cannot change a loyalty program reward target after customers have joined it', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $owner->id]);
    $program = LoyaltyProgram::create([
        'store_id' => $store->id,
        'name' => 'Weekend Card',
        'reward_target' => 7,
        'reward_title' => 'Free weekend drink',
        'brand_color' => '#DC2626',
        'background_color' => '#450A0A',
        'registration_form_config' => Store::defaultRegistrationFormConfig(),
        'sort_order' => 2,
    ]);
    $customer = Customer::factory()->create();

    LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'loyalty_program_id' => $program->id,
        'customer_id' => $customer->id,
    ]);

    $response = $this->from(route('merchant.stores.programs.edit', [$store, $program]))
        ->actingAs($owner)
        ->put(route('merchant.stores.programs.update', [$store, $program]), [
            'name' => $program->name,
            'reward_target' => 9,
            'reward_title' => $program->reward_title,
            'brand_color' => $program->brand_color,
            'background_color' => $program->background_color,
        ]);

    $response->assertRedirect(route('merchant.stores.programs.edit', [$store, $program]));
    $response->assertSessionHasErrors('reward_target');
    expect($program->fresh()->reward_target)->toBe(7);
});

test('free merchant cannot add an additional loyalty card beyond the default card', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($owner)->post(route('merchant.stores.programs.store', $store), [
        'name' => 'Second Card',
        'reward_target' => 6,
        'reward_title' => 'Free muffin',
    ]);

    $response->assertSessionHasErrors('name');
    expect(LoyaltyProgram::where('store_id', $store->id)->count())->toBe(1);
});
