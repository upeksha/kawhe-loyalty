<?php

use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->owner = User::factory()->create();
    $this->store = Store::factory()->for($this->owner)->create(['reward_target' => 3, 'require_verification_for_redemption' => false]);
    $this->card = LoyaltyAccount::factory()->for($this->store)->create(['stamp_count' => 2, 'reward_balance' => 0, 'redeem_token' => null]);
    $this->actingAs($this->owner);
    $this->stamp = ['store_id' => $this->store->id, 'token' => $this->card->public_token, 'count' => 2];
    $event = $this->postJson('/api/v1/stamp', $this->stamp)->assertOk()->assertJsonPath('rewardBalance', 1)->json('undo.stamp_event_id');
    $this->undo = ['store_id' => $this->store->id, 'stamp_event_id' => $event];
});

test('undo restores progress and reward with one audit entry even after retry', function () {
    $this->travel(55)->seconds();
    $this->postJson('/api/v1/stamp/undo', $this->undo)->assertOk()->assertJsonPath('stampCount', 2)->assertJsonPath('rewardBalance', 0);
    $this->travel(10)->seconds();
    $this->postJson('/api/v1/stamp/undo', $this->undo)->assertOk()->assertJsonPath('stampCount', 2);
    expect($this->card->fresh()->redeem_token)->toBeNull();
    expect($this->card->fresh()->last_stamped_at)->toBeNull();
    $this->assertDatabaseCount('stamp_events', 2);
    $this->assertDatabaseHas('points_transactions', ['type' => 'stamp_undo', 'points' => -2, 'user_id' => $this->owner->id]);
    $this->actingAs($this->owner, 'web')->get('/merchant/activity')->assertOk()->assertSee('stamps undone');
});

test('expired undo leaves the earned reward alone', function () {
    $this->travel(61)->seconds();
    $this->postJson('/api/v1/stamp/undo', $this->undo)->assertConflict();
    expect($this->card->fresh()->reward_balance)->toBe(1);
});

test('undo cannot change a card with a later stamp or redemption', function (string $laterAction) {
    $this->travel(6)->seconds();
    if ($laterAction === 'stamp') {
        $this->postJson('/api/v1/stamp', $this->stamp)->assertOk();
    } else {
        $this->postJson('/api/v1/redeem', ['store_id' => $this->store->id, 'token' => $this->card->fresh()->redeem_token])->assertOk();
    }
    $this->postJson('/api/v1/stamp/undo', $this->undo)->assertConflict();
    $this->assertDatabaseMissing('stamp_events', ['type' => 'stamp_undo']);
})->with(['stamp', 'redeem']);

test('a different account cannot undo the stamp', function () {
    $this->actingAs(User::factory()->create());
    $this->postJson('/api/v1/stamp/undo', $this->undo)->assertForbidden();
    expect($this->card->fresh()->reward_balance)->toBe(1);
});
