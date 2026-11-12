<?php

use App\Models\Store;
use App\Models\User;

test('legacy store poster opens the default card while each card keeps its own sharing page', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->for($owner)->create();
    $default = $store->resolvedDefaultProgram();
    $other = $store->loyaltyPrograms()->create([
        'name' => 'Pastry card',
        'reward_target' => 5,
        'reward_title' => 'Free pastry',
    ]);

    $this->actingAs($owner)->get(route('merchant.stores.qr', $store))
        ->assertRedirect(route('merchant.stores.programs.qr', [$store, $default]));
    $this->get(route('merchant.stores.index'))->assertOk()
        ->assertDontSee(route('merchant.stores.qr', $store), false);
    $this->get(route('merchant.stores.programs.qr', [$store, $default]))->assertOk()
        ->assertSee($default->join_url, false)
        ->assertSee('Download A4 poster');
    $this->get(route('merchant.stores.programs.qr', [$store, $other]))->assertOk()
        ->assertSee($other->join_url, false);
});

test('store poster and QR downloads still return downloadable assets', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->for($owner)->create();
    $this->actingAs($owner)->get(route('merchant.stores.qr.pdf', ['store' => $store, 'preview' => 1]))
        ->assertOk()->assertSee($store->name)->assertViewHas('joinUrl', $store->join_url);
    $pdf = $this->get(route('merchant.stores.qr.pdf', $store))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($pdf->getContent())->toStartWith('%PDF-');
    $this->get(route('merchant.stores.qr.image', $store))->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->assertSee('<svg', false);
});

test('another merchant cannot read store sharing or download assets', function () {
    $store = Store::factory()->create();
    $otherOwner = User::factory()->create();
    Store::factory()->for($otherOwner)->create();
    $this->actingAs($otherOwner);
    foreach (['merchant.stores.qr', 'merchant.stores.qr.pdf', 'merchant.stores.qr.image'] as $route) {
        $this->get(route($route, $store))->assertForbidden();
    }
});
