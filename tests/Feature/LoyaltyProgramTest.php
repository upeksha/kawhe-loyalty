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

test('creating a loyalty program inherits store branding assets by default', function () {
    $owner = User::factory()->create(['stripe_id' => 'sub_123']);
    $store = Store::factory()->create([
        'user_id' => $owner->id,
        'logo_path' => 'logos/store-logo.png',
        'pass_logo_path' => 'pass-logos/store-pass-logo.png',
        'pass_hero_image_path' => 'pass-heroes/store-pass-hero.png',
    ]);
    Subscription::create([
        'user_id' => $owner->id,
        'name' => 'default',
        'stripe_id' => 'si_store_assets',
        'stripe_status' => 'active',
        'quantity' => 1,
    ]);

    $this->actingAs($owner)->post(route('merchant.stores.programs.store', $store), [
        'name' => 'Tea Card',
        'reward_target' => 5,
        'reward_title' => 'Free tea',
        'brand_color' => '#2563EB',
        'background_color' => '#0F172A',
        'require_verification_for_redemption' => '1',
        'first_name_enabled' => '1',
    ])->assertRedirect();

    $program = LoyaltyProgram::where('store_id', $store->id)
        ->where('name', 'Tea Card')
        ->first();

    expect($program)->not->toBeNull();
    expect($program->logo_path)->toBe('logos/store-logo.png');
    expect($program->pass_logo_path)->toBe('pass-logos/store-pass-logo.png');
    expect($program->pass_hero_image_path)->toBe('pass-heroes/store-pass-hero.png');
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

test('merchant can download a loyalty card qr as svg', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $owner->id, 'name' => 'My Cafe']);
    $program = $store->resolvedDefaultProgram();

    $response = $this->actingAs($owner)->get(route('merchant.stores.programs.qr.image', [$store, $program]));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/svg+xml');
    $response->assertHeader('Content-Disposition', 'attachment; filename=my-cafe-'.\Illuminate\Support\Str::slug($program->name).'-qr-code.svg');
});

test('merchant can download a loyalty card poster as pdf', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $owner->id, 'name' => 'My Cafe']);
    $program = $store->resolvedDefaultProgram();

    $response = $this->actingAs($owner)->get(route('merchant.stores.programs.qr.pdf', [$store, $program]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});

test('updating the default loyalty card syncs legacy store compatibility fields', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->create([
        'user_id' => $owner->id,
        'reward_target' => 9,
        'reward_title' => 'Free coffee',
        'require_verification_for_redemption' => true,
        'registration_form_config' => Store::defaultRegistrationFormConfig(),
    ]);
    $program = $store->resolvedDefaultProgram();

    $response = $this->actingAs($owner)->put(route('merchant.stores.programs.update', [$store, $program]), [
        'name' => 'Main Coffee Card',
        'reward_target' => 12,
        'reward_title' => 'Free bag of beans',
        'brand_color' => '#111827',
        'background_color' => '#F5F5F4',
        'first_name_enabled' => '1',
        'phone_enabled' => '1',
    ]);

    $response->assertRedirect(route('merchant.stores.programs.edit', [$store, $program]));

    $store->refresh();
    $program->refresh();

    expect($program->reward_target)->toBe(12);
    expect($program->reward_title)->toBe('Free bag of beans');
    expect((bool) $program->require_verification_for_redemption)->toBeFalse();
    expect($program->registration_form_config['phone']['enabled'])->toBeTrue();

    expect($store->reward_target)->toBe(12);
    expect($store->reward_title)->toBe('Free bag of beans');
    expect((bool) $store->require_verification_for_redemption)->toBeFalse();
    expect($store->registration_form_config['phone']['enabled'])->toBeTrue();
});

test('updating a non-default loyalty card does not overwrite store compatibility fields', function () {
    $owner = User::factory()->create(['stripe_id' => 'sub_123']);
    $store = Store::factory()->create([
        'user_id' => $owner->id,
        'reward_target' => 9,
        'reward_title' => 'Free coffee',
        'require_verification_for_redemption' => true,
        'registration_form_config' => Store::defaultRegistrationFormConfig(),
    ]);
    Subscription::create([
        'user_id' => $owner->id,
        'name' => 'default',
        'stripe_id' => 'si_123',
        'stripe_status' => 'active',
        'quantity' => 1,
    ]);

    $program = LoyaltyProgram::create([
        'store_id' => $store->id,
        'name' => 'Weekend Card',
        'reward_target' => 4,
        'reward_title' => 'Free pastry',
        'brand_color' => '#D97706',
        'background_color' => '#431407',
        'registration_form_config' => Store::defaultRegistrationFormConfig(),
        'require_verification_for_redemption' => false,
        'sort_order' => 2,
    ]);

    $response = $this->actingAs($owner)->put(route('merchant.stores.programs.update', [$store, $program]), [
        'name' => 'Weekend Card Updated',
        'reward_target' => 5,
        'reward_title' => 'Free cake slice',
        'brand_color' => '#D97706',
        'background_color' => '#431407',
        'require_verification_for_redemption' => '1',
        'first_name_enabled' => '1',
        'phone_enabled' => '1',
    ]);

    $response->assertRedirect(route('merchant.stores.programs.edit', [$store, $program]));

    $store->refresh();
    $program->refresh();

    expect($program->reward_target)->toBe(5);
    expect($program->reward_title)->toBe('Free cake slice');
    expect((bool) $program->require_verification_for_redemption)->toBeTrue();

    expect($store->reward_target)->toBe(9);
    expect($store->reward_title)->toBe('Free coffee');
    expect((bool) $store->require_verification_for_redemption)->toBeTrue();
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

test('loyalty cards index groups cards by store across all merchant stores', function () {
    $owner = User::factory()->create(['stripe_id' => 'sub_123']);
    Subscription::create([
        'user_id' => $owner->id,
        'name' => 'default',
        'type' => 'default',
        'stripe_id' => 'si_cards_grouped',
        'stripe_status' => 'active',
        'quantity' => 1,
    ]);

    $firstStore = Store::factory()->create(['user_id' => $owner->id, 'name' => 'Downtown Cafe']);
    $secondStore = Store::factory()->create(['user_id' => $owner->id, 'name' => 'Airport Kiosk']);

    LoyaltyProgram::create([
        'store_id' => $firstStore->id,
        'name' => 'Coffee Card',
        'reward_target' => 8,
        'reward_title' => 'Free coffee',
        'brand_color' => '#2563EB',
        'background_color' => '#0F172A',
        'registration_form_config' => Store::defaultRegistrationFormConfig(),
        'sort_order' => 1,
    ]);

    LoyaltyProgram::create([
        'store_id' => $secondStore->id,
        'name' => 'Snack Card',
        'reward_target' => 5,
        'reward_title' => 'Free snack',
        'brand_color' => '#D97706',
        'background_color' => '#431407',
        'registration_form_config' => Store::defaultRegistrationFormConfig(),
        'sort_order' => 1,
    ]);

    $response = $this->actingAs($owner)->get(route('merchant.programs.index'));

    $response->assertOk();
    $response->assertSee('Downtown Cafe', false);
    $response->assertSee('Airport Kiosk', false);
    $response->assertSee('Coffee Card', false);
    $response->assertSee('Snack Card', false);
    $response->assertSee('Cards are grouped by store', false);
});

test('free merchant at card limit does not see add loyalty card button', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($owner)->get(route('merchant.stores.programs.index', $store));

    $response->assertOk();
    $response->assertDontSee('Add Loyalty Card', false);
});

test('free merchant at card limit is redirected from create loyalty card form', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($owner)->get(route('merchant.stores.programs.create', $store));

    $response->assertRedirect(route('merchant.stores.programs.index', $store));
});
