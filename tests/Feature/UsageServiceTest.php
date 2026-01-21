<?php

use App\Models\LoyaltyAccount;
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

function makeAccount(Store $store, array $attributes = []): LoyaltyAccount
{
    return LoyaltyAccount::factory()->create(array_merge([
        'store_id' => $store->id,
    ], $attributes));
}

test('free user under limit can create card', function () {
    $user = User::factory()->create();
    $store = makeStore($user);
    makeAccount($store); // 1 card

    $service = new UsageService();
    expect($service->canCreateCard($user))->toBeTrue();
});

test('free user at limit cannot create card', function () {
    $user = User::factory()->create();
    $store = makeStore($user);
    // create exactly freeLimit cards after cancellation window (non-grandfathered)
    $service = new UsageService();
    $limit = $service->freeLimit();
    LoyaltyAccount::factory()->count($limit)->create([
        'store_id' => $store->id,
    ]);

    expect($service->canCreateCard($user))->toBeFalse();
});

test('active subscriber bypasses limits', function () {
    $user = User::factory()->create(['stripe_id' => 'sub_123']);
    $store = makeStore($user);
    // large number of cards
    LoyaltyAccount::factory()->count(200)->create([
        'store_id' => $store->id,
    ]);

    // create an active subscription record
    Subscription::create([
        'user_id' => $user->id,
        'name' => 'default',
        'stripe_id' => 'si_123',
        'stripe_status' => 'active',
        'quantity' => 1,
    ]);

    $service = new UsageService();
    expect($service->canCreateCard($user))->toBeTrue();
    $stats = $service->getUsageStats($user);
    expect($stats['is_subscribed'])->toBeTrue();
});

test('grandfathered cards excluded after cancellation', function () {
    $user = User::factory()->create(['stripe_id' => 'sub_123']);
    $store = makeStore($user);

    // subscription that ended yesterday
    $endsAt = now()->subDay();
    Subscription::create([
        'user_id' => $user->id,
        'name' => 'default',
        'stripe_id' => 'si_123',
        'stripe_status' => 'canceled',
        'quantity' => 1,
        'ends_at' => $endsAt,
    ]);

    // grandfathered: before ends_at
    makeAccount($store, ['created_at' => $endsAt->copy()->subDay()]);
    // non-grandfathered: after ends_at
    makeAccount($store, ['created_at' => $endsAt->copy()->addDay()]);

    $service = new UsageService();
    expect($service->grandfatheredCardsCount($user))->toBe(1);
    expect($service->cardsCountForUser($user, includeGrandfathered: false))->toBe(1);
    expect($service->canCreateCard($user))->toBeTrue(); // only one non-grandfathered card
});

