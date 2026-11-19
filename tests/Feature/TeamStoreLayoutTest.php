<?php

use App\Models\Store;
use App\Models\StoreStaff;
use App\Models\StoreStaffInvitation;
use App\Models\User;

test('team panels separate store memberships and invitation states with scoped actions', function () {
    $owner = User::factory()->create();
    $first = Store::factory()->for($owner)->create(['name' => 'TEST First Cafe']);
    $second = Store::factory()->for($owner)->create(['name' => 'TEST Second Cafe']);
    $staff = User::factory()->create(['name' => 'TEST Shared Barista']);
    $one = StoreStaff::create(['store_id' => $first->id, 'user_id' => $staff->id, 'can_stamp' => true, 'can_redeem' => false]);
    StoreStaff::create(['store_id' => $second->id, 'user_id' => $staff->id, 'can_stamp' => false, 'can_redeem' => true]);
    $private = Store::factory()->create(['name' => 'TEST Private Cafe']);
    StoreStaff::create(['store_id' => $private->id, 'user_id' => User::factory()->create(['name' => 'TEST Hidden Member'])->id, 'can_stamp' => true, 'can_redeem' => true]);
    foreach ([[$first, 'pending@example.invalid', now()->addDays(3)], [$second, 'expired@example.invalid', now()->subDay()]] as [$store, $email, $expires]) {
        StoreStaffInvitation::create(['store_id' => $store->id, 'invited_by' => $owner->id, 'email' => $email, 'can_stamp' => true, 'can_redeem' => false, 'token_hash' => hash('sha256', $email), 'expires_at' => $expires]);
    }
    $response = $this->actingAs($owner)->get(route('merchant.staff.index'))->assertOk()->assertSee('TEST First Cafe')->assertSee('TEST Second Cafe')->assertSee('Pending')->assertSee('Expired')->assertDontSee('TEST Private Cafe')->assertDontSee('TEST Hidden Member');
    $dom = new DOMDocument; @$dom->loadHTML($response->getContent()); $xpath = new DOMXPath($dom);
    expect($xpath->query('//section[starts-with(@id,"team-store-")]')->length)->toBe(2);
    foreach ([$first, $second] as $store) {
        $panel = $xpath->query('//section[@id="team-store-'.$store->id.'"]')->item(0);
        expect($panel->textContent)->toContain('TEST Shared Barista');
        expect($xpath->query('.//form[@action="'.route('merchant.staff.invite', $store).'"]', $panel)->length)->toBeGreaterThan(0);
    }
    $firstPanel = $xpath->query('//section[@id="team-store-'.$first->id.'"]')->item(0);
    expect($firstPanel->textContent)->toContain('pending@example.invalid')->not->toContain('expired@example.invalid');
    expect($xpath->query('.//button[@aria-controls="member-permissions-'.$one->id.'"]', $firstPanel)->length)->toBe(1);
});
