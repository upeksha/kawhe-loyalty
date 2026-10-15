<?php

use App\Models\Store;
use App\Models\User;

test('card design keeps uploads and its guarded continue button within the form', function () {
    $owner = User::factory()->create();
    Store::factory()->for($owner)->create(['onboarding_step' => 'card_design', 'onboarding_completed_at' => null, 'logo_path' => null, 'pass_logo_path' => null, 'pass_hero_image_path' => null]);
    $response = $this->actingAs($owner)->get(route('merchant.onboarding.wizard.card-design'))->assertOk();
    $response->assertSee('Wallet banner')->assertSee('Card preview')->assertDontSee('Launch quality')->assertDontSee('Tap to expand');
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//form[@id="card-design-form"]//button[@type="submit"]')->length)->toBe(1);
    expect($xpath->query('//form[@id="card-design-form"]//input[@type="file" and @required]')->length)->toBe(2);
    expect($xpath->query('//input[@type="text" and @pattern]')->length)->toBe(2);
});

test('saved card design images do not require replacement on revisiting onboarding', function () {
    $owner = User::factory()->create();
    Store::factory()->for($owner)->create(['onboarding_step' => 'card_design', 'onboarding_completed_at' => null, 'logo_path' => 'logos/test.png', 'pass_logo_path' => 'pass-logos/test.png', 'pass_hero_image_path' => 'pass-heroes/test.png']);
    $response = $this->actingAs($owner)->get(route('merchant.onboarding.wizard.card-design'))->assertOk();
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    expect((new DOMXPath($dom))->query('//input[@type="file" and @required]')->length)->toBe(0);
});
