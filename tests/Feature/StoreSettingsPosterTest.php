<?php

use App\Models\Store;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('store poster reads current default card branding without changing its join destination or legacy data', function () {
    config(['filesystems.assets_disk' => 'public']);
    Storage::fake('public');
    $owner = User::factory()->create();
    $oldLogo = UploadedFile::fake()->image('old.png', 8, 8)->store('logos', 'public');
    $newLogo = UploadedFile::fake()->image('new.png', 12, 12)->store('logos', 'public');
    $newHero = UploadedFile::fake()->image('hero.png', 16, 16)->store('heroes', 'public');
    $store = Store::factory()->for($owner)->create(['brand_color' => '#112233', 'background_color' => '#223344', 'reward_title' => 'Old reward', 'pass_logo_path' => $oldLogo]);
    $program = $store->resolvedDefaultProgram();
    $program->update(['brand_color' => '#345678', 'background_color' => '#456789', 'reward_title' => 'New coffee reward', 'pass_logo_path' => $newLogo, 'pass_hero_image_path' => $newHero]);
    $previewUrl = route('merchant.stores.qr.pdf', ['store' => $store, 'preview' => 1]);
    $response = $this->actingAs($owner)->get($previewUrl)->assertOk();
    $response->assertViewHas('joinUrl', $store->join_url)->assertViewHas('program', fn ($value) => $value->id === $program->id)
        ->assertViewHas('logoDataUrl', 'data:image/png;base64,'.base64_encode(Storage::disk('public')->get($newLogo)))
        ->assertViewHas('heroImageDataUrl', 'data:image/png;base64,'.base64_encode(Storage::disk('public')->get($newHero)))
        ->assertSee('background: #345678', false)->assertSee('New coffee reward')->assertDontSee('Old reward');

    // Light card accent colours use that card's background, not the old store background.
    $program->update(['brand_color' => '#ffffff', 'background_color' => '#135724', 'reward_title' => 'Updated coffee reward']);
    $this->get($previewUrl)->assertOk()->assertSee('background: #135724', false)->assertSee('Updated coffee reward');
    $pdf = $this->get(route('merchant.stores.qr.pdf', $store))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($pdf->getContent())->toStartWith('%PDF-');
    expect($store->fresh()->brand_color)->toBe('#112233')->and($store->fresh()->pass_logo_path)->toBe($oldLogo);
});

test('store poster retains inherited artwork when the default card has none', function () {
    config(['filesystems.assets_disk' => 'public']);
    Storage::fake('public');
    $owner = User::factory()->create();
    $logo = UploadedFile::fake()->image('inherited.png')->store('logos', 'public');
    $store = Store::factory()->for($owner)->create(['pass_logo_path' => $logo]);
    $store->resolvedDefaultProgram()->update(['pass_logo_path' => null, 'logo_path' => null]);
    $this->actingAs($owner)->get(route('merchant.stores.qr.pdf', ['store' => $store, 'preview' => 1]))->assertOk()
        ->assertViewHas('logoDataUrl', 'data:image/png;base64,'.base64_encode(Storage::disk('public')->get($logo)));
});

test('store settings keep management and wallet actions accessible without legacy controls', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->for($owner)->create();
    $response = $this->actingAs($owner)->get(route('merchant.stores.edit', $store))->assertOk()->assertDontSee('Legacy fallback settings');
    $dom = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML($response->getContent());
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//details')->length)->toBe(0);
    foreach (['refresh-wallets', 'destroy'] as $action) {
        expect($xpath->query('//section[@aria-labelledby="wallet-help-title" or @aria-labelledby="store-management-title"]//form[@method="POST" and @action="'.route('merchant.stores.'.$action, $store).'"]/input[@name="_token"]')->length)->toBe(1);
    }
    expect($xpath->query('//form[@id="store-edit-form"]/input[@name="_method" and @value="PUT"]')->length)->toBe(1);

    Store::factory()->for($owner)->create();
    $store->delete();
    $this->get(route('merchant.stores.edit', $store))->assertOk()->assertSee('Restore store')->assertSee(route('merchant.stores.restore', $store), false)->assertDontSee('Refresh store Wallets');
    $this->get(route('merchant.stores.qr.pdf', $store))->assertRedirect(route('merchant.stores.edit', $store));
});

test('three style posters use card background and readable text instead of legacy accent', function ($style, $background, $expectedBackground) {
    config(['app.url' => 'https://testing.kawhe.shop', 'app.env' => 'testing']);
    $owner = User::factory()->create();
    $store = Store::factory()->for($owner)->create();
    $program = $store->resolvedDefaultProgram();
    $program->update(['card_design' => $style, 'background_color' => $background, 'brand_color' => '#ff00ff']);
    [$bg, $ink] = \App\Support\CardDesign::colors($program);
    expect($bg)->toBe($expectedBackground);
    foreach ([route('merchant.stores.qr.pdf', ['store' => $store, 'preview' => 1]), route('merchant.stores.programs.qr.pdf', ['store' => $store, 'program' => $program, 'preview' => 1])] as $url) {
        $this->actingAs($owner)->get($url)->assertOk()
            ->assertSee('background: '.$expectedBackground, false)
            ->assertSee('color: '.$ink, false)
            ->assertDontSee('background: #ff00ff', false)
            ->assertSee('Scan to join. Collect stamps. Earn rewards.');
    }
})->with([
    ['simple', '#ffffff', '#FFFFFF'],
    ['illustrated', '#263e30', '#263E30'],
    ['photo', '#ffffff', '#182820'],
]);
