<?php

use App\Jobs\UpdateWalletPassJob;
use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\PointsTransaction;
use App\Models\StampEvent;
use App\Models\Store;
use App\Models\User;
use App\Services\Loyalty\StampLoyaltyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('stamp increments stamp_count by 1', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id, 'reward_target' => 10]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'stamp_count' => 0,
    ]);

    $service = new StampLoyaltyService();
    $result = $service->stamp($account, $user);

    $account->refresh();
    expect($account->stamp_count)->toBe(1);
    expect($result->stampCount)->toBe(1);
    expect($result->rewardBalance)->toBe(0);
    expect($result->rewardEarned)->toBeFalse();
});

test('stamp increments stamp_count by custom count', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id, 'reward_target' => 10]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'stamp_count' => 0,
    ]);

    $service = new StampLoyaltyService();
    $result = $service->stamp($account, $user, count: 3);

    $account->refresh();
    expect($account->stamp_count)->toBe(3);
    expect($result->stampCount)->toBe(3);
});

test('when stamp_count reaches reward_target, stamp_count resets and reward_balance increments', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id, 'reward_target' => 10]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'stamp_count' => 9, // One stamp away from reward
        'reward_balance' => 0,
    ]);

    $service = new StampLoyaltyService();
    $result = $service->stamp($account, $user);

    $account->refresh();
    expect($account->stamp_count)->toBe(0); // Reset after reaching target
    expect($account->reward_balance)->toBe(1); // Incremented
    expect($result->stampCount)->toBe(0);
    expect($result->rewardBalance)->toBe(1);
    expect($result->rewardEarned)->toBeTrue();
});

test('handles overshoot when stamp_count exceeds reward_target', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id, 'reward_target' => 10]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'stamp_count' => 9,
        'reward_balance' => 0,
    ]);

    $service = new StampLoyaltyService();
    $result = $service->stamp($account, $user, count: 5); // 9 + 5 = 14, should give 1 reward and 4 remaining

    $account->refresh();
    expect($account->stamp_count)->toBe(4); // 14 - 10 = 4
    expect($account->reward_balance)->toBe(1);
    expect($result->stampCount)->toBe(4);
    expect($result->rewardBalance)->toBe(1);
    expect($result->rewardEarned)->toBeTrue();
});

test('creates audit logs in stamp_events table', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
    ]);

    $service = new StampLoyaltyService();
    $idempotencyKey = 'test-key-123';
    $service->stamp($account, $user, idempotencyKey: $idempotencyKey);

    $this->assertDatabaseHas('stamp_events', [
        'loyalty_account_id' => $account->id,
        'store_id' => $store->id,
        'user_id' => $user->id,
        'type' => 'stamp',
        'count' => 1,
        'idempotency_key' => $idempotencyKey,
    ]);
});

test('creates audit logs in points_transactions table if exists', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
    ]);

    $service = new StampLoyaltyService();
    $idempotencyKey = 'test-key-456';
    $service->stamp($account, $user, count: 2, idempotencyKey: $idempotencyKey);

    $this->assertDatabaseHas('points_transactions', [
        'loyalty_account_id' => $account->id,
        'store_id' => $store->id,
        'user_id' => $user->id,
        'type' => 'earn',
        'points' => 2,
        'idempotency_key' => $idempotencyKey,
    ]);
});

test('dispatches wallet update job after successful stamp', function () {
    Queue::fake();

    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
    ]);

    $service = new StampLoyaltyService();
    $service->stamp($account, $user);

    Queue::assertPushed(UpdateWalletPassJob::class, function ($job) use ($account) {
        return $job->loyaltyAccountId === $account->id;
    });
});

test('idempotency_key prevents double stamping', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'stamp_count' => 0,
    ]);

    $service = new StampLoyaltyService();
    $idempotencyKey = 'unique-key-789';

    // First stamp
    $result1 = $service->stamp($account, $user, idempotencyKey: $idempotencyKey);
    $account->refresh();
    $stampCountAfterFirst = $account->stamp_count;

    // Second stamp with same idempotency key
    $result2 = $service->stamp($account, $user, idempotencyKey: $idempotencyKey);
    $account->refresh();

    expect($result2->isDuplicate)->toBeTrue();
    expect($account->stamp_count)->toBe($stampCountAfterFirst); // Should not have incremented again

    // Should only have one stamp event
    $eventCount = StampEvent::where('idempotency_key', $idempotencyKey)->count();
    expect($eventCount)->toBe(1);
});

test('throws validation exception if staff does not own store', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $owner->id]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
    ]);

    $attacker = User::factory()->create();
    Store::factory()->create(['user_id' => $attacker->id]); // Attacker has their own store

    $service = new StampLoyaltyService();

    expect(fn() => $service->stamp($account, $attacker))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('super admin can stamp any store', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $owner->id]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'stamp_count' => 0,
    ]);

    $superAdmin = User::factory()->create(['is_super_admin' => true]);

    $service = new StampLoyaltyService();
    $result = $service->stamp($account, $superAdmin);

    $account->refresh();
    expect($account->stamp_count)->toBe(1);
});

test('uses store reward_target if available', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id, 'reward_target' => 5]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'stamp_count' => 4,
    ]);

    $service = new StampLoyaltyService();
    $result = $service->stamp($account, $user);

    $account->refresh();
    expect($account->stamp_count)->toBe(0); // 4 + 1 = 5, which is the target
    expect($account->reward_balance)->toBe(1);
});

test('uses config default reward_target if store has none', function () {
    config(['loyalty.reward_target' => 8]);

    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id, 'reward_target' => null]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'stamp_count' => 7,
    ]);

    $service = new StampLoyaltyService();
    $result = $service->stamp($account, $user);

    $account->refresh();
    expect($account->stamp_count)->toBe(0); // 7 + 1 = 8, which is the config default
    expect($account->reward_balance)->toBe(1);
});

test('updates last_stamped_at timestamp', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'last_stamped_at' => null,
    ]);

    $service = new StampLoyaltyService();
    $service->stamp($account, $user);

    $account->refresh();
    expect($account->last_stamped_at)->not->toBeNull();
});

test('increments version for optimistic locking', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'version' => 5,
    ]);

    $service = new StampLoyaltyService();
    $service->stamp($account, $user);

    $account->refresh();
    expect($account->version)->toBe(6);
});

test('sets reward_available_at and redeem_token when reward is earned', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id, 'reward_target' => 10]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'stamp_count' => 9,
        'reward_balance' => 0,
        'reward_available_at' => null,
        'redeem_token' => null,
    ]);

    $service = new StampLoyaltyService();
    $service->stamp($account, $user);

    $account->refresh();
    expect($account->reward_available_at)->not->toBeNull();
    expect($account->redeem_token)->not->toBeNull();
});

test('clears reward_available_at and redeem_token when no rewards remain', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id, 'reward_target' => 10]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'stamp_count' => 0,
        'reward_balance' => 0,
        'reward_available_at' => now(),
        'redeem_token' => 'existing-token',
    ]);

    $service = new StampLoyaltyService();
    $service->stamp($account, $user, count: 5); // Not enough for reward

    $account->refresh();
    expect($account->reward_available_at)->toBeNull();
    expect($account->redeem_token)->toBeNull();
});
