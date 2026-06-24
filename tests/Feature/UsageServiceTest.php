<?php

use App\Models\LoyaltyProgram;
use App\Models\Store;
use App\Models\User;
use App\Services\Billing\UsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Subscription;

uses(RefreshDatabase::class);

function makeStore(User $user): Store
{
    return Store::factory()->create(['user_id' => $user->id]);
}

function makeProgram(Store $store, array $attributes = []): LoyaltyProgram
{
    $timestamps = array_intersect_key($attributes, array_flip(['created_at', 'updated_at']));
    unset($attributes['created_at'], $attributes['updated_at']);

    $program = LoyaltyProgram::create(array_merge([
        'store_id' => $store->id,
        'name' => 'Program '.fake()->word(),
        'reward_target' => 8,
        'reward_title' => 'Free coffee',
        'brand_color' => '#0EA5E9',
        'background_color' => '#1F2937',
        'registration_form_config' => Store::defaultRegistrationFormConfig(),
        'sort_order' => 2,
    ], $attributes));

    if ($timestamps !== []) {
        $program->forceFill($timestamps)->saveQuietly();
    }

    return $program;
}

test('free user with only the default card cannot create another card', function () {
    $user = User::factory()->create();
    makeStore($user); // store factory creates the default program

    $service = new UsageService();
    expect($service->canCreateProgram($user))->toBeFalse();
    expect($service->programLimitForUser($user))->toBe(1);
});

test('free user with no stores can create a first card', function () {
    $user = User::factory()->create();

    $service = new UsageService();
    expect($service->canCreateProgram($user))->toBeTrue();
});

test('active subscriber can create up to three cards', function () {
    $user = User::factory()->create(['stripe_id' => 'sub_123']);
    $store = makeStore($user);

    Subscription::create([
        'user_id' => $user->id,
        'name' => 'default',
        'stripe_id' => 'si_123',
        'stripe_status' => 'active',
        'quantity' => 1,
    ]);

    makeProgram($store);
    makeProgram($store);

    $service = new UsageService();
    expect($service->canCreateProgram($user))->toBeFalse();
    expect($service->programLimitForUser($user))->toBe(3);
    expect($service->getUsageStats($user)['is_subscribed'])->toBeTrue();
});

test('grandfathered loyalty cards are excluded after cancellation', function () {
    $user = User::factory()->create(['stripe_id' => 'sub_123']);
    $store = makeStore($user);

    $endsAt = now()->subDay();
    Subscription::create([
        'user_id' => $user->id,
        'name' => 'default',
        'stripe_id' => 'si_123',
        'stripe_status' => 'canceled',
        'quantity' => 1,
        'ends_at' => $endsAt,
    ]);

    makeProgram($store, [
        'name' => 'Grandfathered Card',
        'created_at' => $endsAt->copy()->subDay(),
        'updated_at' => $endsAt->copy()->subDay(),
    ]);

    makeProgram($store, [
        'name' => 'Recent Card',
        'created_at' => $endsAt->copy()->addDay(),
        'updated_at' => $endsAt->copy()->addDay(),
    ]);

    $service = new UsageService();
    expect($service->grandfatheredProgramsCount($user))->toBe(1);
    expect($service->programsCountForUser($user, includeGrandfathered: false))->toBe(2);
    expect($service->canCreateProgram($user))->toBeFalse();
});
