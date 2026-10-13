<?php

use App\Events\StampUpdated;
use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\PointsTransaction;
use App\Models\StampEvent;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

uses(Tests\TestCase::class);

beforeEach(function () {
    expect(DB::connection()->getDriverName())->toBe('sqlite');
    expect(DB::connection()->getDatabaseName())->toBe(':memory:');
    $this->artisan('migrate:fresh', ['--force' => true]);
    Queue::fake();
    $this->owner = User::factory()->create();
    $this->store = Store::factory()->create(['user_id' => $this->owner->id]);
    $this->account = LoyaltyAccount::factory()->create([
        'store_id' => $this->store->id, 'customer_id' => Customer::factory()->create()->id,
        'stamp_count' => 0, 'reward_balance' => 1, 'verified_at' => now(),
        'redeem_token' => str_repeat('a', 32), 'last_stamped_at' => null,
    ]);
    $this->actingAs($this->owner);
});

test('transient deadlocks retry without duplicate balances or premature broadcasts', function (string $action) {
    $attempts = 0;
    $levels = [];
    Event::listen(StampUpdated::class, function () use (&$levels) {
        $levels[] = DB::transactionLevel();
    });
    StampEvent::creating(function () use (&$attempts) {
        if (++$attempts === 1) {
            throw new PDOException('Deadlock found when trying to get lock; try restarting transaction', 40001);
        }
    });
    try {
        $this->postJson('/api/v1/'.$action, [
            'store_id' => $this->store->id,
            'token' => $action === 'stamp' ? $this->account->public_token : $this->account->redeem_token,
            'idempotency_key' => 'retry-'.$action,
        ])->assertOk()->assertJson(['success' => true]);
        expect($attempts)->toBe(2);
        expect($levels)->toBe([0]);
        expect(StampEvent::count())->toBe(1);
        expect(PointsTransaction::count())->toBe(1);
        expect($this->account->fresh()->stamp_count)->toBe($action === 'stamp' ? 1 : 0);
        expect($this->account->fresh()->reward_balance)->toBe($action === 'stamp' ? 1 : 0);
    } finally {
        Event::forget('eloquent.creating: '.StampEvent::class);
    }
})->with(['stamp', 'redeem']);
