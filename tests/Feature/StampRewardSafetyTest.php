<?php

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\PointsTransaction;
use App\Models\StampEvent;
use App\Models\Store;
use App\Models\User;
use App\Services\Loyalty\StampLoyaltyService;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->owner = User::factory()->create();
    $this->store = Store::factory()->create(['user_id' => $this->owner->id]);
    $this->account = LoyaltyAccount::factory()->create([
        'store_id' => $this->store->id, 'customer_id' => Customer::factory()->create()->id,
        'stamp_count' => 0, 'reward_balance' => 2, 'verified_at' => now(),
        'redeem_token' => str_repeat('a', 32), 'last_stamped_at' => null,
        'reward_redeemed_at' => null,
    ]);
    $this->actingAs($this->owner);
});

test('stale account snapshots cannot bypass the atomic stamp duplicate window', function () {
    $stale = $this->account->fresh();
    $service = app(StampLoyaltyService::class);
    expect($service->stamp($this->account, $this->owner, idempotencyKey: 'first')->isDuplicate)->toBeFalse();
    expect($service->stamp($stale, $this->owner, idempotencyKey: 'second')->isDuplicate)->toBeTrue();
    expect($this->account->fresh()->stamp_count)->toBe(1);
    expect(StampEvent::count())->toBe(1);
    expect(PointsTransaction::count())->toBe(1);
});

test('a delayed network retry cannot add stamps twice but a new purchase can', function () {
    $body = ['token' => $this->account->public_token, 'idempotency_key' => 'retry'];
    $this->postJson('/stamp', $body)->assertOk()->assertJson(['success' => true]);
    $this->travel(10)->seconds();
    $this->postJson('/stamp', $body)->assertOk()->assertJson(['status' => 'duplicate']);
    $this->postJson('/stamp', array_merge($body, ['idempotency_key' => 'new-purchase']))->assertOk()->assertJson(['success' => true]);
    expect($this->account->fresh()->stamp_count)->toBe(2);
});

test('reusing a stamp key for different quantities or accounts is rejected', function () {
    $body = ['token' => $this->account->public_token, 'idempotency_key' => 'bound-key'];
    $this->postJson('/stamp', $body)->assertOk();
    $this->postJson('/stamp', $body + ['count' => 3])->assertUnprocessable();
    $other = LoyaltyAccount::factory()->create(['store_id' => $this->store->id, 'last_stamped_at' => null, 'stamp_count' => 0]);
    $this->postJson('/stamp', ['token' => $other->public_token, 'idempotency_key' => 'bound-key'])->assertUnprocessable();
    expect($other->fresh()->stamp_count)->toBe(0);
});

test('a redeem retry reports duplicate and cannot spend another reward', function () {
    $body = ['store_id' => $this->store->id, 'token' => $this->account->redeem_token, 'idempotency_key' => 'redeem-retry'];
    $this->postJson('/redeem', $body)->assertOk()->assertJson(['success' => true]);
    $this->postJson('/redeem', $body)->assertOk()->assertJson(['status' => 'duplicate', 'success' => false]);
    $this->postJson('/redeem', array_merge($body, ['idempotency_key' => 'another-staff']))->assertStatus(404);
    expect($this->account->fresh()->reward_balance)->toBe(1);
    expect(PointsTransaction::where('type', 'redeem')->count())->toBe(1);
});

test('repeated manual codes cannot spend a second reward in the duplicate window', function () {
    $this->account->update(['manual_entry_code' => 'SAFE']);
    $body = ['store_id' => $this->store->id, 'token' => 'SAFE'];
    $this->postJson('/redeem', $body)->assertOk()->assertJson(['success' => true]);
    $this->postJson('/redeem', $body)->assertOk()->assertJson(['status' => 'duplicate']);
    expect($this->account->fresh()->reward_balance)->toBe(1);
    $this->travel(6)->seconds();
    $this->postJson('/redeem', $body)->assertOk()->assertJson(['success' => true]);
    expect($this->account->fresh()->reward_balance)->toBe(0);
});

test('a redemption request key cannot be reused for another card', function () {
    $body = ['store_id' => $this->store->id, 'token' => $this->account->redeem_token, 'idempotency_key' => 'redeem-bound'];
    $this->postJson('/redeem', $body)->assertOk();
    $this->postJson('/redeem', array_merge($body, ['token' => 'another-card-token']))->assertUnprocessable();
});
