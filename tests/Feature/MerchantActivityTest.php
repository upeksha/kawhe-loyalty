<?php

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\StampEvent;
use App\Models\Store;
use App\Models\User;

function createActivityEvent(Store $store, User $actor, string $customerName, string $type = 'stamp', int $count = 1): StampEvent
{
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => Customer::factory()->create(['name' => $customerName])->id,
    ]);

    return StampEvent::create([
        'store_id' => $store->id, 'loyalty_account_id' => $account->id, 'user_id' => $actor->id,
        'type' => $type, 'count' => $count,
    ]);
}

test('activity displays existing stamps and rewards with the actual staff member and store', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->for($owner)->create(['name' => 'Owner Cafe']);
    $staff = User::factory()->create(['is_staff_account' => true, 'name' => 'Jamie Staff']);
    createActivityEvent($store, $staff, 'Alex Customer', 'stamp', 3);
    createActivityEvent($store, $owner, 'Sam Customer', 'redeem');

    $this->actingAs($owner)->get(route('merchant.activity.index'))->assertOk()
        ->assertSee('3 stamps added')->assertSee('1 reward redeemed')->assertSee('Alex Customer')
        ->assertSee('Sam Customer')->assertSee('Jamie Staff')->assertSee('Owner Cafe');
});

test('activity is scoped to owned active stores even when foreign store parameters are supplied', function () {
    $owner = User::factory()->create();
    $ownStore = Store::factory()->for($owner)->create();
    $otherStore = Store::factory()->create();
    $archivedStore = Store::factory()->for($owner)->create();
    createActivityEvent($ownStore, $owner, 'Visible Customer');
    createActivityEvent($otherStore, $otherStore->user, 'Foreign Customer');
    createActivityEvent($archivedStore, $owner, 'Archived Customer');
    $archivedStore->delete();

    $this->actingAs($owner)->get(route('merchant.activity.index', ['store_id' => $otherStore->id]))
        ->assertOk()->assertSee('Visible Customer')->assertDontSee('Foreign Customer')->assertDontSee('Archived Customer');
});

test('activity has a useful empty state', function () {
    $owner = User::factory()->create();
    Store::factory()->for($owner)->create();
    $this->actingAs($owner)->get(route('merchant.activity.index'))->assertOk()->assertSee('No activity yet');
});

test('activity paginates in stable newest first order without exposing request metadata', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->for($owner)->create();
    $event = createActivityEvent($store, $owner, '<script>alert("test")</script>');
    $event->update(['ip_address' => '192.0.2.44', 'user_agent' => 'private-user-agent']);
    for ($i = 0; $i < 25; $i++) {
        $copy = $event->replicate();
        $copy->save();
    }
    $page = $this->actingAs($owner)->get(route('merchant.activity.index'))->assertOk();
    $page->assertViewHas('events', fn ($events) => $events->count() === 25 && $events->total() === 26 && $events->first()->id === $copy->id);
    $page->assertDontSee('<script>alert("test")</script>', false)->assertDontSee('192.0.2.44')->assertDontSee('private-user-agent');
    $this->get(route('merchant.activity.index', ['page' => 2]))->assertOk()
        ->assertViewHas('events', fn ($events) => $events->count() === 1 && $events->first()->id === $event->id);
});

test('activity requires merchant authentication and verification', function () {
    $this->get(route('merchant.activity.index'))->assertRedirect(route('login'));
    $unverified = User::factory()->unverified()->create();
    $this->actingAs($unverified)->get(route('merchant.activity.index'))->assertRedirect(route('verification.notice'));
});

test('staff cannot access merchant activity or support history', function () {
    $staff = User::factory()->create(['is_staff_account' => true]);
    $this->actingAs($staff)->get(route('merchant.activity.index'))->assertForbidden();
    $this->get(route('merchant.support.index'))->assertForbidden();
});
