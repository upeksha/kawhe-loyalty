<?php

use App\Models\Customer;
use App\Models\LoyaltyAccount;
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

function makeSubscriber(User $user): void
{
    $user->forceFill(['stripe_id' => 'cus_'.fake()->uuid()])->save();

    Subscription::create([
        'user_id' => $user->id,
        'name' => 'default',
        'stripe_id' => 'sub_'.fake()->uuid(),
        'stripe_status' => 'active',
        'quantity' => 1,
    ]);
}

test('free user with only the default card cannot create another card on the same store', function () {
    $user = User::factory()->create();
    $store = makeStore($user);

    $service = new UsageService;
    expect($service->canCreateProgram($user, $store))->toBeFalse();
    expect($service->canCreateStore($user))->toBeFalse();
    expect($service->programsPerStoreLimitForUser($user))->toBe(1);
});

test('free user with no stores can create a first store', function () {
    $user = User::factory()->create();

    $service = new UsageService;
    expect($service->canCreateStore($user))->toBeTrue();
    expect($service->canCreateProgram($user))->toBeTrue();
});

test('active subscriber can create multiple cards per store up to the pro cap', function () {
    $user = User::factory()->create();
    $store = makeStore($user);
    makeSubscriber($user);

    for ($i = 0; $i < 4; $i++) {
        makeProgram($store);
    }

    $service = new UsageService;
    expect($service->programsCountForStore($store))->toBe(5);
    expect($service->canCreateProgram($user, $store))->toBeFalse();
    expect($service->programsPerStoreLimitForUser($user))->toBe(5);
});

test('active subscriber can create up to three stores', function () {
    $user = User::factory()->create();
    makeSubscriber($user);

    makeStore($user);
    makeStore($user);
    makeStore($user);

    $service = new UsageService;
    expect($service->storesCountForUser($user))->toBe(3);
    expect($service->canCreateStore($user))->toBeFalse();
    expect($service->storesLimitForUser($user))->toBe(3);
});

test('free plan blocks new customer joins at one hundred per program', function () {
    $user = User::factory()->create();
    $store = makeStore($user);
    $program = $store->loyaltyPrograms()->first();

    for ($i = 0; $i < 100; $i++) {
        LoyaltyAccount::create([
            'store_id' => $store->id,
            'loyalty_program_id' => $program->id,
            'customer_id' => Customer::factory()->create()->id,
        ]);
    }

    $service = new UsageService;
    expect($service->customersCountForProgram($program))->toBe(100);
    expect($service->canAcceptNewCustomer($program))->toBeFalse();
});

test('pro plan allows unlimited new customers per program', function () {
    $user = User::factory()->create();
    makeSubscriber($user);
    $store = makeStore($user);
    $program = $store->loyaltyPrograms()->first();

    for ($i = 0; $i < 120; $i++) {
        LoyaltyAccount::create([
            'store_id' => $store->id,
            'loyalty_program_id' => $program->id,
            'customer_id' => Customer::factory()->create()->id,
        ]);
    }

    $service = new UsageService;
    expect($service->canAcceptNewCustomer($program))->toBeTrue();
    expect($service->customersPerProgramLimitForUser($user))->toBeNull();
});

test('get usage stats exposes customer join capacity', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $program = $store->loyaltyPrograms()->first();

    $service = new UsageService;
    $stats = $service->getUsageStats($user);

    expect($stats)->toHaveKey('can_accept_new_customer')
        ->and($stats['can_accept_new_customer'])->toBeTrue()
        ->and($stats['can_create_store'])->toBeFalse()
        ->and($stats['can_create_program'])->toBeFalse();

    for ($i = 0; $i < 100; $i++) {
        LoyaltyAccount::create([
            'store_id' => $store->id,
            'loyalty_program_id' => $program->id,
            'customer_id' => Customer::factory()->create()->id,
        ]);
    }

    $statsAtLimit = $service->getUsageStats($user);

    expect($statsAtLimit['can_accept_new_customer'])->toBeFalse();
});

test('usage stats primary store card message reflects primary store limit not global capacity', function () {
    $user = User::factory()->create();
    makeSubscriber($user);

    $primaryStore = makeStore($user);
    $secondaryStore = makeStore($user);

    for ($i = 0; $i < 4; $i++) {
        makeProgram($primaryStore);
    }

    $service = new UsageService;

    expect($service->programsCountForStore($primaryStore))->toBe(5)
        ->and($service->canCreateProgramForStore($user, $primaryStore))->toBeFalse()
        ->and($service->canCreateProgram($user))->toBeTrue();

    $stats = $service->getUsageStats($user);

    expect($stats['primary_store_programs_count'])->toBe(5)
        ->and($stats['primary_store_can_create_program'])->toBeFalse()
        ->and($stats['can_create_program'])->toBeTrue()
        ->and($stats['programs_usage_percentage'])->toBe(100)
        ->and($stats['stores_card_usage'])->toHaveCount(2)
        ->and(collect($stats['stores_card_usage'])->firstWhere('store_id', $primaryStore->id)['programs_count'])->toBe(5)
        ->and(collect($stats['stores_card_usage'])->firstWhere('store_id', $secondaryStore->id)['can_create_program'])->toBeTrue();
});

test('grandfathered loyalty cards remain after cancellation but new cards are gated on free limits', function () {
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

    $service = new UsageService;
    expect($service->grandfatheredProgramsCount($user))->toBe(1);
    expect($service->canCreateProgram($user, $store))->toBeFalse();
});
