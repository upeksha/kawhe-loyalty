<?php

use App\Jobs\RefreshProgramWalletsJob;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Cashier\Subscription;

function coffeeDesignMerchant(): array
{
    config(['app.env' => 'testing', 'app.url' => 'https://testing.kawhe.shop']);
    Queue::fake();
    $owner = User::factory()->create(['stripe_id' => 'cus_coffee_test']);
    $store = Store::factory()->for($owner)->create();
    Subscription::create(['user_id' => $owner->id, 'name' => 'default', 'stripe_id' => 'sub_coffee_'.$owner->id, 'stripe_status' => 'active', 'quantity' => 1]);

    return [$owner, $store];
}

test('merchant can create and switch coffee style while retaining reward balances and classic assets', function () {
    [$owner, $store] = coffeeDesignMerchant();
    $data = ['name' => 'Coffee club', 'reward_target' => 10, 'reward_title' => 'A large coffee and pastry', 'card_design' => 'illustrated_coffee', 'require_verification_for_redemption' => '1'];
    $this->actingAs($owner)->post(route('merchant.stores.programs.store', $store), $data)->assertSessionHasNoErrors();
    $program = $store->loyaltyPrograms()->where('name', 'Coffee club')->firstOrFail();
    expect($program->card_design)->toBe('illustrated_coffee');
    $account = LoyaltyAccount::factory()->create(['store_id' => $store->id, 'loyalty_program_id' => $program->id, 'stamp_count' => 1, 'reward_balance' => 2]);
    $hash = $program->wallet_design_hash;
    $hero = $program->pass_hero_image_path;
    $this->actingAs($owner)->get(route('merchant.stores.programs.edit', [$store, $program]))->assertOk()->assertSee('Illustrated — patterns and artwork')->assertSee('coffee-preview-panel');
    $data['card_design'] = 'classic';
    $this->put(route('merchant.stores.programs.update', [$store, $program]), $data)->assertSessionHasNoErrors();
    expect($program->fresh()->card_design)->toBe('classic');
    expect($program->fresh()->wallet_design_hash)->not->toBe($hash);
    expect($program->fresh()->pass_hero_image_path)->toBe($hero);
    expect($account->fresh()->stamp_count)->toBe(1);
    expect($account->fresh()->reward_balance)->toBe(2);
    Queue::assertPushed(RefreshProgramWalletsJob::class);
    $this->get('/c/'.$account->public_token)->assertOk()->assertDontSee('id="coffee-sample-art"', false);
});

test('design selection rejects unknown styles mismatched environments and other merchants', function () {
    [$owner, $store] = coffeeDesignMerchant();
    $program = $store->resolvedDefaultProgram();
    $data = ['name' => 'Coffee', 'reward_target' => $program->reward_target, 'reward_title' => 'Coffee', 'card_design' => 'unknown'];
    $url = route('merchant.stores.programs.update', [$store, $program]);
    $this->actingAs($owner)->put($url, $data)->assertSessionHasErrors('card_design');
    config(['app.env' => 'production']);
    $data['card_design'] = 'illustrated_coffee';
    $this->put($url, $data)->assertSessionHasErrors('card_design');
    config(['app.env' => 'testing']);
    [$other] = coffeeDesignMerchant();
    $this->actingAs($other)->put($url, $data)->assertNotFound();
    expect($program->fresh()->card_design)->toBe('classic');
});

test('onboarding coffee design needs logos but no banner and synchronizes default program', function () {
    [$owner, $store] = coffeeDesignMerchant();
    $store->update(['onboarding_step' => 'card_design', 'onboarding_completed_at' => null, 'logo_path' => 'logos/existing.png', 'pass_logo_path' => 'logos/existing.png', 'pass_hero_image_path' => null]);
    $data = ['card_design' => 'illustrated_coffee', 'brand_color' => '#f2ecd7', 'background_color' => '#263e30'];
    $this->actingAs($owner)->post(route('merchant.onboarding.wizard.card-design.store'), $data)->assertSessionHasNoErrors()->assertRedirect(route('merchant.onboarding.wizard.customer-form'));
    expect($store->fresh()->card_design)->toBe('illustrated_coffee');
    expect($store->fresh()->resolvedDefaultProgram()->card_design)->toBe('illustrated_coffee');
    $data['card_design'] = 'classic';
    $this->post(route('merchant.onboarding.wizard.card-design.store'), $data)->assertSessionHasErrors('pass_hero_image');
});
