<?php

use App\Models\LoyaltyAccount;
use App\Models\StampEvent;
use App\Models\Store;
use App\Models\User;
use App\Support\MerchantLaunchChecklist;
use Illuminate\Support\Str;

test('unverified owner sees only email instructions before café setup', function () {
    $owner = User::factory()->unverified()->create();
    Store::factory()->for($owner)->create(['onboarding_step' => 'store_basics']);

    $this->actingAs($owner)->get(route('verification.notice'))->assertOk()
        ->assertSee('Verify your email before using your merchant dashboard')
        ->assertDontSee('Your café launch checklist')
        ->assertDontSee('Continue setup');
});

test('checklist follows saved café and card setup progress', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->for($owner)->create(['onboarding_step' => 'store_basics']);

    $this->actingAs($owner)->get(route('merchant.onboarding.wizard.store-basics'))->assertOk()
        ->assertSee('Step 2 of 6')
        ->assertSee('Confirm your email')
        ->assertSee('Complete a test stamp')
        ->assertDontSee('Your café launch checklist');

    $this->post(route('merchant.onboarding.wizard.store-basics.store'), [
        'name' => $store->name,
        'reward_target' => 7,
        'reward_title' => 'Free coffee',
    ])->assertRedirect(route('merchant.onboarding.wizard.card-design'));

    $this->get(route('merchant.onboarding.wizard.card-design'))->assertOk()
        ->assertSee('Step 3 of 6')
        ->assertDontSee('Your café launch checklist');

    $store->forceFill(['onboarding_step' => Store::ONBOARDING_STEP_CARD_READY])->save();
    $this->get(route('merchant.onboarding.wizard.card-ready'))->assertOk()
        ->assertSee('Step 4 of 6')
        ->assertSee('Download poster (PDF)')
        ->assertSee('Continue to app install');

    $this->post(route('merchant.onboarding.wizard.complete'))
        ->assertRedirect(route('merchant.onboarding.wizard.card-ready'));
    expect($store->fresh()->onboarding_completed_at)->toBeNull();

    $this->get(route('merchant.stores.qr.pdf', $store))->assertOk();
    $this->post(route('merchant.onboarding.wizard.complete'))
        ->assertRedirect(route('merchant.onboarding.launch.show', $store));
    $this->get(route('merchant.onboarding.launch.show', $store))->assertOk()
        ->assertSee('Step 5 of 6')
        ->assertDontSee('Your café launch checklist');
});

test('poster preview does not count but a completed download persists', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->for($owner)->create(['onboarding_completed_at' => now()]);

    $this->actingAs($owner)->get(route('merchant.stores.qr.pdf', ['store' => $store, 'preview' => 1]))->assertOk();
    expect($store->fresh()->launch_poster_downloaded_at)->toBeNull();

    $this->get(route('merchant.stores.qr.pdf', $store))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($store->fresh()->launch_poster_downloaded_at)->not->toBeNull();

    $this->get(route('merchant.dashboard'))->assertOk()
        ->assertSee('Next: Install Kawhe Merchant')
        ->assertSee('4 of 6 complete')
        ->assertDontSee('View all setup steps');
});

test('a card-specific join poster also counts as a downloaded poster', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->for($owner)->create(['onboarding_completed_at' => now()]);
    $program = $store->resolvedDefaultProgram();

    $this->actingAs($owner)
        ->get(route('merchant.stores.programs.qr.pdf', ['store' => $store, 'program' => $program, 'preview' => 1]))
        ->assertOk();
    expect($store->fresh()->launch_poster_downloaded_at)->toBeNull();

    $this->get(route('merchant.stores.programs.qr.pdf', [$store, $program]))
        ->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($store->fresh()->launch_poster_downloaded_at)->not->toBeNull();
});

test('only the café owner can confirm merchant app installation', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $store = Store::factory()->for($owner)->create(['onboarding_completed_at' => now()]);

    $this->actingAs($other)->post(route('merchant.onboarding.launch.app-installed', $store))->assertForbidden();
    expect($store->fresh()->launch_merchant_app_installed_at)->toBeNull();

    $this->actingAs($owner)->post(route('merchant.onboarding.launch.app-installed', $store))
        ->assertRedirect(route('merchant.stores.qr', $store));
    expect($store->fresh()->launch_merchant_app_installed_at)->toBeNull();
    $this->get(route('merchant.onboarding.launch.show', $store))
        ->assertRedirect(route('merchant.stores.qr', $store).'#download-poster');

    $store->forceFill(['launch_poster_downloaded_at' => now()])->save();

    $this->actingAs($owner)->from(route('merchant.dashboard'))
        ->post(route('merchant.onboarding.launch.app-installed', $store))
        ->assertRedirect(route('merchant.dashboard'));
    expect($store->fresh()->launch_merchant_app_installed_at)->not->toBeNull();
});

test('the launch page guides the owner through app installation and a test stamp', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $store = Store::factory()->for($owner)->create([
        'onboarding_completed_at' => now(),
        'launch_poster_downloaded_at' => now(),
    ]);
    $unfinished = Store::factory()->for($owner)->create(['onboarding_step' => \App\Http\Controllers\MerchantOnboardingWizardController::STEP_STORE_BASICS]);

    $this->actingAs($other)->get(route('merchant.onboarding.launch.show', $store))->assertForbidden();
    $this->actingAs($owner)->get(route('merchant.onboarding.launch.show', $unfinished))
        ->assertRedirect(route('merchant.onboarding.wizard.index'));
    $this->actingAs($owner)->get(route('merchant.onboarding.launch.show', $store))->assertOk()
        ->assertSee('Step 5 of 6')
        ->assertSee('I’ve installed Kawhe Merchant')
        ->assertDontSee('Open customer join page');

    $this->from(route('merchant.onboarding.launch.show', $store))
        ->post(route('merchant.onboarding.launch.app-installed', $store))
        ->assertRedirect(route('merchant.onboarding.launch.show', $store));
    $this->get(route('merchant.onboarding.launch.show', $store))->assertOk()
        ->assertSee('Step 6 of 6')
        ->assertSee('Open join page')
        ->assertSee('Continue to dashboard')
        ->assertSee('Check progress')
        ->assertDontSee('I’ve installed Kawhe Merchant');
});

test('a successful stamp completes the checklist for its own café', function () {
    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();
    $store = Store::factory()->for($owner)->create(['onboarding_completed_at' => now()]);
    $otherStore = Store::factory()->for($otherOwner)->create(['onboarding_completed_at' => now()]);
    $store->forceFill([
        'launch_poster_downloaded_at' => now(),
        'launch_merchant_app_installed_at' => now(),
    ])->save();

    $otherAccount = LoyaltyAccount::factory()->for($otherStore)->create();
    StampEvent::create([
        'loyalty_account_id' => $otherAccount->id,
        'store_id' => $otherStore->id,
        'user_id' => $otherOwner->id,
        'type' => 'stamp',
        'count' => 1,
        'idempotency_key' => (string) Str::uuid(),
    ]);
    expect(app(MerchantLaunchChecklist::class)->forUser($owner)['ready'])->toBeFalse();

    $account = LoyaltyAccount::factory()->for($store)->create();
    StampEvent::create([
        'loyalty_account_id' => $account->id,
        'store_id' => $store->id,
        'user_id' => $owner->id,
        'type' => 'stamp',
        'count' => 1,
        'idempotency_key' => (string) Str::uuid(),
    ]);

    $this->actingAs($owner)->get(route('merchant.dashboard'))->assertOk()
        ->assertDontSee('Finish setting up '.$store->name)
        ->assertDontSee('View all setup steps');
});

test('only the dashboard shows launch progress for an unfinished café', function () {
    $owner = User::factory()->create();
    $first = Store::factory()->for($owner)->create(['onboarding_completed_at' => now()]);
    $second = Store::factory()->for($owner)->create(['onboarding_completed_at' => now()]);
    $first->forceFill(['launch_poster_downloaded_at' => now()])->save();

    $this->actingAs($owner)->get(route('merchant.stores.qr', $second))->assertOk()
        ->assertSee($second->name)
        ->assertDontSee('3 of 6 complete');

    $this->get(route('merchant.customers.index'))->assertOk()
        ->assertDontSee('Finish setting up '.$first->name)
        ->assertDontSee('Continue setup');

    $this->get(route('merchant.dashboard'))->assertOk()
        ->assertSeeInOrder(['Welcome back', 'Active customers', 'Finish setting up '.$first->name])
        ->assertDontSee('View all setup steps')
        ->assertSee('Continue setup');

    expect(app(MerchantLaunchChecklist::class)->forUser($owner)['store']->id)->toBe($first->id);
});
