<?php

use App\Models\Store;
use App\Models\User;

// Keep the sharing tools pointed at the same store when the workspace changes.
test('store sharing workspace preserves join destination and download and recovery actions', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->for($owner)->create();
    $response = $this->actingAs($owner)->get(route('merchant.stores.qr', $store))->assertOk();
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML($response->getContent());
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $dom = new DOMXPath($document);

    expect($dom->query('//input[@id="join-link"]')->item(0)->getAttribute('value'))->toBe($store->join_url);
    expect($dom->query('//a[@href="'.$store->join_url.'"]')->length)->toBe(1);
    expect($dom->query('//a[@href="'.route('merchant.stores.qr.pdf', ['store' => $store, 'preview' => 1]).'"]')->length)->toBe(1);
    expect($dom->query('//form[@method="GET" and @action="'.route('merchant.stores.qr.image', $store).'"]')->length)->toBe(1);
    expect($dom->query('//details//form[@method="POST" and @action="'.route('merchant.stores.refresh-wallets', $store).'"]/input[@name="_token"]')->length)->toBe(1);
    expect($dom->query('//details[@open]')->length)->toBe(0);
    $response->assertSee(route('merchant.stores.qr.pdf', $store), false);
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
