<?php

use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;
use App\Services\Billing\AccountDataDeletion;
use App\Services\Wallet\Artwork\CardStyleArtwork;
use App\Services\Wallet\Artwork\WalletDesignHasher;
use App\Support\CardDesign;
use App\Support\CoffeeCardSample;
use App\Support\StoreAssets;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['app.env' => 'testing', 'app.url' => 'https://testing.kawhe.shop', 'filesystems.assets_disk' => 'public']);
    Storage::fake('public');
    Queue::fake();
});

test('stamp colour saves independently from text and can return to automatic', function (string $style) {
    $store = Store::factory()->create();
    $program = $store->resolvedDefaultProgram();
    $account = LoyaltyAccount::factory()->create(['store_id' => $store->id, 'loyalty_program_id' => $program->id, 'stamp_count' => 2, 'reward_balance' => 1]);
    $payload = ['name' => 'Custom colours', 'card_design' => $style, 'background_color' => '#263E30',
        'card_text_color' => '#FFFFFF', 'stamp_color' => '#FFD166', 'reward_target' => $program->reward_target, 'reward_title' => $program->reward_title];
    if ($style === 'photo') {
        $payload['card_photo'] = UploadedFile::fake()->image('cafe.jpg', 900, 600);
    }
    $url = route('merchant.stores.programs.update', [$store, $program]);
    $this->actingAs($store->user)->put($url, $payload)->assertSessionHasNoErrors();
    $program->refresh();
    expect($program->stamp_color)->toBe('#FFD166')->and($program->card_text_color)->toBe('#FFFFFF')
        ->and($store->fresh()->stamp_color)->toBe('#FFD166')
        ->and(CardDesign::options($program)['stamp_color'])->toBe('#FFD166')
        ->and($account->fresh()->stamp_count)->toBe(2)->and($account->fresh()->reward_balance)->toBe(1);
    $this->get(route('merchant.stores.programs.edit', [$store, $program]))->assertOk()
        ->assertSee('Choose stamp colour')->assertSee('Use automatic stamp colour');
    $this->put($url, ['name' => 'Custom colours', 'stamp_color' => '', 'reward_target' => $program->reward_target, 'reward_title' => $program->reward_title])->assertSessionHasNoErrors();
    expect($program->fresh()->stamp_color)->toBeNull()
        ->and(CardDesign::options($program->fresh())['stamp_color'])->toBe('#FFFFFF');
})->with(['simple', 'illustrated', 'photo']);

test('a stamp colour must be visible on the chosen background and Photo overlay', function () {
    $store = Store::factory()->create();
    $program = $store->resolvedDefaultProgram();
    $this->actingAs($store->user);
    $url = route('merchant.stores.programs.update', [$store, $program]);
    foreach (['bad', '#263E30', '#GGGGGG'] as $color) {
        $this->put($url, ['name' => 'Contrast', 'background_color' => '#263E30', 'stamp_color' => $color])
            ->assertSessionHasErrors('stamp_color');
    }
    $this->put($url, ['name' => 'Photo', 'card_design' => 'photo', 'background_color' => '#FFFFFF',
        'stamp_color' => '#000000', 'card_photo' => UploadedFile::fake()->image('cafe.jpg', 900, 600)])
        ->assertSessionHasErrors('stamp_color');
    expect($program->fresh()->stamp_color)->toBeNull();
});

test('reward uploads are validated saved reused and preserved when switching built in icons', function (string $style) {
    $store = Store::factory()->create();
    $program = $store->resolvedDefaultProgram();
    $program->update(['card_design' => $style]);
    if ($style === 'photo') {
        $program->update(['card_photo_path' => StoreAssets::storeUploaded(UploadedFile::fake()->image('photo.jpg'), 'card-designs')]);
    }
    $this->actingAs($store->user);
    $url = route('merchant.stores.programs.update', [$store, $program]);
    $payload = ['name' => 'Custom reward', 'reward_icon' => 'custom', 'reward_target' => $program->reward_target, 'reward_title' => $program->reward_title];
    $this->put($url, $payload)->assertSessionHasErrors('reward_icon_upload');
    $this->put($url, $payload + ['reward_icon_upload' => UploadedFile::fake()->create('icon.svg', 2, 'image/svg+xml')])
        ->assertSessionHasErrors('reward_icon_upload');
    $this->put($url, $payload + ['reward_icon_upload' => UploadedFile::fake()->image('reward.png', 128, 128)])
        ->assertSessionHasNoErrors();
    $program->refresh();
    $path = $program->reward_icon_path;
    expect($path)->not->toBeNull()->and($program->reward_icon)->toBe('custom')
        ->and($store->fresh()->reward_icon_path)->toBe($path)
        ->and(CardDesign::options($program)['custom_reward_icon'])->toBe(StoreAssets::get($path));
    $this->put($url, $payload)->assertSessionHasNoErrors();
    $this->put($url, ['name' => 'Gift', 'reward_icon' => 'gift', 'reward_target' => $program->reward_target, 'reward_title' => $program->reward_title])->assertSessionHasNoErrors();
    expect($program->fresh()->reward_icon_path)->toBe($path);
    $this->put($url, $payload)->assertSessionHasNoErrors();
    expect($program->fresh()->reward_icon_path)->toBe($path);
    expect(AccountDataDeletion::IMAGE_FIELDS)->toContain('reward_icon_path');
})->with(['simple', 'illustrated', 'photo']);

test('custom reward artwork only replaces the ready state and stamp colour changes artwork on all platforms', function (string $platform) {
    $model = Store::factory()->make(['card_design' => 'simple', 'background_color' => '#263E30']);
    $original = CardDesign::options($model);
    $model->stamp_color = '#FFD166';
    $colored = CardDesign::options($model);
    $renderer = app(CardStyleArtwork::class);
    expect($renderer->render(7, 4, false, $platform, $colored))->not->toBe($renderer->render(7, 4, false, $platform, $original));
    $upload = UploadedFile::fake()->image('reward.png', 64, 64);
    $png = file_get_contents($upload->getRealPath());
    $reward = [...$colored, 'reward_icon' => 'custom', 'custom_reward_icon' => $png];
    expect($renderer->render(7, 4, false, $platform, $reward))->toBe($renderer->render(7, 4, false, $platform, $colored));
    expect($renderer->render(7, 0, true, $platform, $reward))->not->toBe($renderer->render(7, 0, true, $platform, $colored));
})->with(['web', 'apple', 'google']);

test('onboarding previews uploaded rewards without storing files and saves both new settings', function () {
    $store = Store::factory()->create(['onboarding_step' => Store::ONBOARDING_STEP_CARD_DESIGN]);
    $this->actingAs($store->user);
    $payload = ['store_id' => $store->id, 'card_design' => 'simple', 'background_color' => '#263E30',
        'reward_target' => 7, 'stamp_color' => '#FFD166', 'reward_icon' => 'custom', 'platform' => 'apple', 'ready' => true,
        'reward_icon_upload' => UploadedFile::fake()->image('reward.png', 128, 128)];
    $files = Storage::disk('public')->allFiles();
    $response = $this->post(route('merchant.card-design.preview'), $payload)->assertOk()->assertHeader('Content-Type', 'image/png');
    expect(Storage::disk('public')->allFiles())->toBe($files);
    $payload['brand_color'] = '#0EA5E9';
    $payload['logo'] = UploadedFile::fake()->image('logo.png', 240, 240);
    $payload['pass_logo'] = UploadedFile::fake()->image('wallet.png', 200, 80);
    $this->post(route('merchant.onboarding.wizard.card-design.store'), $payload)->assertSessionHasNoErrors()
        ->assertRedirect(route('merchant.onboarding.wizard.customer-form'));
    $program = $store->fresh()->resolvedDefaultProgram();
    expect($program->stamp_color)->toBe('#FFD166')->and($program->reward_icon)->toBe('custom')
        ->and($program->reward_icon_path)->not->toBeNull();
    $hash = app(WalletDesignHasher::class)->hash($program, 'logo', 'hero');
    $account = LoyaltyAccount::factory()->create(['store_id' => $store->id, 'loyalty_program_id' => $program->id, 'stamp_count' => 0, 'reward_balance' => 1]);
    $before = CoffeeCardSample::artwork($account, 'apple');
    $program->update(['stamp_color' => '#FFFFFF']);
    expect(CoffeeCardSample::artwork($account->fresh(), 'apple'))->not->toBe($before)
        ->and(app(WalletDesignHasher::class)->hash($program->fresh(), 'logo', 'hero'))->not->toBe($hash);
});

test('another merchant cannot preview or save a reward upload for a card', function () {
    $store = Store::factory()->create();
    $program = $store->resolvedDefaultProgram();
    $other = Store::factory()->create();
    $this->actingAs($other->user);
    $this->post(route('merchant.card-design.preview'), ['program_id' => $program->id,
        'reward_icon_upload' => UploadedFile::fake()->image('reward.png')])->assertNotFound();
    $this->put(route('merchant.stores.programs.update', [$store, $program]), ['name' => 'Unauthorized',
        'reward_icon' => 'custom', 'reward_icon_upload' => UploadedFile::fake()->image('reward.png')])->assertNotFound();
    expect($program->fresh()->reward_icon_path)->toBeNull()->and($program->fresh()->stamp_color)->toBeNull();
});
