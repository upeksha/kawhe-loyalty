<?php

use App\Models\LoyaltyAccount;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

test('fresh coffee cafe completes verified onboarding and issues a customer card with wallet actions', function (string $style) {
    config(['app.env' => 'testing', 'app.url' => 'https://testing.kawhe.shop']);
    \Illuminate\Support\Facades\Storage::fake('public');
    Mail::fake();
    Notification::fake();
    Queue::fake();
    $this->post('/register', ['name' => 'Fresh Cafe Owner', 'email' => 'fresh-owner@example.com', 'store_name' => 'Fresh Coffee Cafe', 'password' => 'test-password123', 'password_confirmation' => 'test-password123'])->assertRedirect(route('verification.notice'));
    $owner = User::where('email', 'fresh-owner@example.com')->sole();
    $verify = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), ['id' => $owner->id, 'hash' => sha1($owner->email)], absolute: false);
    $this->get($verify)->assertOk()->assertSee('Verify my email');
    expect($owner->fresh()->hasVerifiedEmail())->toBeFalse();
    $this->post($verify)->assertRedirect();
    expect($owner->fresh()->hasVerifiedEmail())->toBeTrue();
    $this->post(route('merchant.onboarding.wizard.store-basics.store'), ['name' => 'Fresh Coffee Cafe', 'reward_target' => 6, 'reward_title' => 'Free large coffee and a freshly baked pastry of your choice'])->assertRedirect(route('merchant.onboarding.wizard.card-design'));
    $this->get(route('merchant.onboarding.wizard.card-design'))->assertOk()->assertSee('Illustrated — patterns and artwork');
    $design = [
        'card_design' => $style, 'brand_color' => '#0EA5E9', 'background_color' => '#263e30',
        'card_pattern' => 'botanical', 'stamp_icon' => $style === 'photo' ? 'custom' : 'star',
        'logo' => UploadedFile::fake()->image('logo.png', 240, 240),
        'pass_logo' => UploadedFile::fake()->image('wallet.png', 480, 150),
    ];
    if ($style === 'photo') {
        $design['card_photo'] = UploadedFile::fake()->image('cafe.jpg', 900, 600);
        $design['stamp_icon_upload'] = UploadedFile::fake()->image('stamp.png', 100, 100);
    }
    $this->post(route('merchant.onboarding.wizard.card-design.store'), $design)->assertSessionHasNoErrors()->assertRedirect(route('merchant.onboarding.wizard.customer-form'));
    $this->post(route('merchant.onboarding.wizard.customer-form.store'), ['first_name_enabled' => '1'])->assertRedirect(route('merchant.onboarding.wizard.card-ready'));
    $this->get(route('merchant.onboarding.wizard.card-ready'))->assertOk()->assertSee('Your loyalty card is ready');
    $store = $owner->stores()->sole();
    $program = $store->resolvedDefaultProgram();
    expect($program->card_design)->toBe($style);
    expect($program->stamp_icon)->toBe(match ($style) {
        'simple' => 'coffee', 'photo' => 'custom', default => 'star'
    });
    expect($program->card_pattern)->toBe('botanical');
    if ($style === 'photo') {
        expect($program->card_photo_path)->not->toBeNull();
        expect($program->stamp_icon_path)->not->toBeNull();
    }
    expect($program->pass_hero_image_path)->toBeNull();
    $this->post(route('merchant.onboarding.wizard.complete'))->assertRedirect(route('merchant.stores.qr', $store));
    expect($store->fresh()->onboarding_completed_at)->not->toBeNull();
    $this->post('/logout');
    $this->get($program->join_url)->assertRedirect();
    $this->get(route('join.index', ['slug' => $program->slug, 't' => $program->join_token]))->assertOk()->assertSee('--program-bg: '.'#263E30', false);
    $this->post(route('join.store', ['slug' => $program->slug, 't' => $program->join_token]), ['email' => 'fresh-customer@example.com', 'first_name' => 'Fresh', 'name' => 'Fresh Customer'])->assertRedirect();
    $account = LoyaltyAccount::where('loyalty_program_id', $program->id)->sole();
    expect($account->stamp_count)->toBe(0);
    expect($account->reward_balance)->toBe(0);
    $this->get('/c/'.$account->public_token)->assertOk()->assertSee('coffee-sample-art')->assertSee('web-6-0.png')->assertSee('Save to Wallet');
    $this->getJson('/api/card/'.$account->public_token)->assertOk()->assertJsonPath('reward_target', 6);
    foreach (['apple', 'google', 'web'] as $platform) {
        $art = \App\Support\CoffeeCardSample::artwork($account, $platform);
        expect(\App\Support\StoreAssets::exists($art))->toBeTrue();
    }
})->with(['simple', 'illustrated', 'photo']);

test('coffee palette applies consistently without overwriting classic colours or enabling production', function () {
    config(['app.env' => 'testing', 'app.url' => 'https://testing.kawhe.shop']);
    $store = \App\Models\Store::factory()->create(['brand_color' => '#0EA5E9', 'background_color' => '#1F2937']);
    $program = $store->resolvedDefaultProgram();
    $program->update(['card_design' => 'illustrated_coffee']);
    $account = LoyaltyAccount::factory()->create(['store_id' => $store->id, 'loyalty_program_id' => $program->id]);
    expect(\App\Support\ProgramBranding::resolve($program, $store)->bg)->toBe('#263E30');
    expect(\App\Support\ProgramBranding::resolveFromAccount($account)->bg)->toBe('#263E30');
    expect($program->fresh()->background_color)->toBe('#1F2937');
    config(['app.env' => 'production']);
    expect(\App\Support\ProgramBranding::resolve($program, $store)->bg)->toBe('#1F2937');
    config(['app.env' => 'testing']);
    $program->update(['card_design' => 'classic']);
    expect(\App\Support\ProgramBranding::resolve($program, $store)->bg)->toBe('#1F2937');
});
