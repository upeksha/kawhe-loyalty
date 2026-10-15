<?php

use App\Models\Store;
use App\Models\User;

test('reward setup shows a visible stamp-based summary and preserves custom targets', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->for($owner)->create(['onboarding_completed_at' => null, 'onboarding_step' => 'store_basics', 'reward_target' => 12, 'reward_title' => 'Free pastry']);
    $this->actingAs($owner)->get(route('merchant.onboarding.wizard.store-basics'))
        ->assertOk()->assertSee('Your café decides what earns a stamp.')
        ->assertSee('Your reward at a glance')->assertSee('Custom')->assertSee('Free pastry')
        ->assertDontSee('Buy ')->assertDontSee('Tap to expand');
    $this->post(route('merchant.onboarding.wizard.store-basics.store'), ['name' => $store->name, 'reward_title' => 'Free pastry', 'reward_target' => 12])
        ->assertRedirect(route('merchant.onboarding.wizard.card-design'));
    expect($store->fresh()->reward_target)->toBe(12);
    expect($store->fresh()->resolvedDefaultProgram()->reward_title)->toBe('Free pastry');
});

test('invalid reward setup preserves values and expands invalid store fields', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner)->from(route('merchant.onboarding.wizard.store-basics'))
        ->post(route('merchant.onboarding.wizard.store-basics.store'), ['name' => '', 'reward_title' => 'Free pastry', 'reward_target' => '2.5'])
        ->assertSessionHasErrors(['name', 'reward_target']);
    $this->get(route('merchant.onboarding.wizard.store-basics'))->assertOk()
        ->assertSee('Free pastry')->assertSee('2.5')->assertSee('showStoreDetails: true', false);
    expect($owner->stores()->count())->toBe(0);
});
