<?php

use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;

test('existing card editor shares the visual designer while keeping issued card protections', function (string $style) {
    config(['app.env' => 'testing', 'app.url' => 'https://testing.kawhe.shop']);
    $owner = User::factory()->create();
    $store = Store::factory()->for($owner)->create();
    $program = $store->resolvedDefaultProgram();
    $program->update(['card_design' => $style, 'background_color' => '#263E30', 'stamp_shape' => 'square', 'reward_icon' => 'gift']);
    $account = LoyaltyAccount::factory()->create(['store_id' => $store->id, 'loyalty_program_id' => $program->id, 'stamp_count' => 3, 'reward_balance' => 1]);
    $response = $this->actingAs($owner)->get(route('merchant.stores.programs.edit', [$store, $program]))->assertOk()
        ->assertSee('Edit card appearance')->assertSee('Illustrated')->assertSee('Photo')
        ->assertSee('Sample Wallet pass. QR and manual code are for preview only.')
        ->assertSee('Locked to protect existing customer progress.')->assertSee('Customer sign-up form')
        ->assertSee('Require email verification before redemption')->assertSee('Save loyalty card');
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    $form = $xpath->query('//form[@id="loyalty-card-form"]')->item(0);
    expect($form->getAttribute('action'))->toBe(route('merchant.stores.programs.update', [$store, $program]));
    expect($xpath->query('.//input[@name="program_id"]', $form)->length)->toBe(1);
    expect($xpath->query('.//input[@name="preview_card_style" and @value="simple"]', $form)->length)->toBe(1);
    foreach (['logo', 'pass_logo', 'card_photo', 'stamp_icon_upload', 'reward_icon_upload'] as $name) {
        expect($xpath->query('.//input[@type="file" and @name="'.$name.'"]', $form)->length)->toBe(1);
    }
    expect($xpath->query('.//button[@type="submit"]', $form)->length)->toBe(1)
        ->and($xpath->query('.//input[@id="reward_target" and @readonly]', $form)->length)->toBe(1)
        ->and($xpath->query('//aside[contains(@class,"kawhe-setup-preview")]//*[@id="loyalty-card-preview"]')->length)->toBe(1)
        ->and($xpath->query('//form//form')->length)->toBe(0)
        ->and($account->fresh()->stamp_count)->toBe(3)->and($account->fresh()->reward_balance)->toBe(1);
})->with(['simple', 'illustrated', 'photo', 'classic']);
