<?php

use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;
use App\Services\Wallet\Artwork\CardStyleArtwork;
use App\Support\CardDesign;
use App\Support\CoffeeCardSample;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['app.env' => 'testing', 'app.url' => 'https://testing.kawhe.shop', 'filesystems.assets_disk' => 'public']);
    Storage::fake('public');
    Queue::fake();
});

function cardStyleOwner(): array
{
    $owner = User::factory()->create();
    $store = Store::factory()->for($owner)->create();

    return [$owner, $store, $store->resolvedDefaultProgram()];
}

test('all three styles save and render without changing customer balances', function (string $style) {
    [$owner,$store,$program] = cardStyleOwner();
    $account = LoyaltyAccount::factory()->create(['store_id' => $store->id, 'loyalty_program_id' => $program->id, 'stamp_count' => 2, 'reward_balance' => 1]);
    $data = ['name' => 'My card', 'reward_target' => $program->reward_target, 'reward_title' => 'A large coffee and a freshly baked pastry', 'card_design' => $style, 'card_pattern' => 'waves', 'stamp_icon' => 'heart', 'background_color' => '#FAEDD7', 'require_verification_for_redemption' => 1];
    if ($style === 'photo') {
        $data['card_photo'] = UploadedFile::fake()->image('cafe.jpg', 1000, 700);
    }
    $this->actingAs($owner)->put(route('merchant.stores.programs.update', [$store, $program]), $data)->assertSessionHasNoErrors();
    $program->refresh();
    expect($program->card_design)->toBe($style);
    expect($program->stamp_icon)->toBe($style === 'simple' ? 'coffee' : 'heart');
    expect($account->fresh()->stamp_count)->toBe(2);
    expect($account->fresh()->reward_balance)->toBe(1);
    expect($store->fresh()->card_design)->toBe($style);
    $account->refresh();
    foreach (['web' => [720, 470], 'apple' => [1125, 432], 'google' => [1032, 812]] as $platform => $size) {
        $path = CoffeeCardSample::artwork($account, $platform);
        expect(array_slice(getimagesizefromstring(\App\Support\StoreAssets::get($path)), 0, 2))->toBe($size);
    }
    $this->get('/c/'.$account->public_token)->assertOk()->assertSee('coffee-sample-art');
})->with(['simple', 'illustrated', 'photo']);

test('photo and custom icon uploads are required validated and persist on editing', function () {
    [$owner,$store,$program] = cardStyleOwner();
    $this->actingAs($owner);
    $url = route('merchant.stores.programs.update', [$store, $program]);
    $data = ['name' => 'Photo card', 'reward_target' => $program->reward_target, 'reward_title' => 'Coffee', 'card_design' => 'photo', 'stamp_icon' => 'custom'];
    $this->put($url, $data)->assertSessionHasErrors(['card_photo', 'stamp_icon_upload']);
    $this->put($url, $data + ['card_photo' => UploadedFile::fake()->create('evil.svg', 1, 'image/svg+xml'), 'stamp_icon_upload' => UploadedFile::fake()->create('icon.svg', 1, 'image/svg+xml')])->assertSessionHasErrors(['card_photo', 'stamp_icon_upload']);
    $this->put($url, $data + ['card_photo' => UploadedFile::fake()->image('photo.png', 500, 400), 'stamp_icon_upload' => UploadedFile::fake()->image('icon.png', 100, 100)])->assertSessionHasNoErrors();
    $program->refresh();
    $photo = $program->card_photo_path;
    $icon = $program->stamp_icon_path;
    expect($photo)->not->toBeNull();
    expect($icon)->not->toBeNull();
    $this->put($url, $data)->assertSessionHasNoErrors();
    expect($program->fresh()->card_photo_path)->toBe($photo);
    expect($program->fresh()->stamp_icon_path)->toBe($icon);
});

test('onboarding saves a photo and custom stamp without requiring a wallet banner', function () {
    [$owner,$store,$program] = cardStyleOwner();
    $store->update(['onboarding_step' => 'card_design', 'onboarding_completed_at' => null, 'logo_path' => 'logo.png', 'pass_logo_path' => 'logo.png', 'pass_hero_image_path' => null]);
    $this->actingAs($owner)->post(route('merchant.onboarding.wizard.card-design.store'), ['card_design' => 'photo', 'stamp_icon' => 'custom', 'brand_color' => '#123456', 'background_color' => '#263e30', 'card_photo' => UploadedFile::fake()->image('photo.jpg', 600, 400), 'stamp_icon_upload' => UploadedFile::fake()->image('icon.png', 100, 100)])->assertSessionHasNoErrors()->assertRedirect(route('merchant.onboarding.wizard.customer-form'));
    expect($store->fresh()->resolvedDefaultProgram()->card_photo_path)->toBe($store->fresh()->card_photo_path);
    expect($store->fresh()->resolvedDefaultProgram()->stamp_icon)->toBe('custom');
});

test('preview works during onboarding is tenant scoped and stores no uploaded files', function () {
    [$owner,$store,$program] = cardStyleOwner();
    $store->update(['onboarding_step' => 'card_design', 'onboarding_completed_at' => null]);
    $data = ['store_id' => $store->id, 'card_design' => 'photo', 'background_color' => '#263e30', 'reward_target' => 10, 'platform' => 'apple', 'ready' => 0, 'card_photo' => UploadedFile::fake()->image('test.jpg', 600, 400)];
    $before = Storage::disk('public')->allFiles();
    $this->actingAs($owner)->post(route('merchant.card-design.preview'), $data)->assertOk()->assertHeader('Content-Type', 'image/png');
    expect(Storage::disk('public')->allFiles())->toBe($before);
    $this->actingAs(User::factory()->create())->post(route('merchant.card-design.preview'), $data)->assertNotFound();
});

test('artwork caches vary by design and simple always uses coffee', function () {
    [$owner,$store,$program] = cardStyleOwner();
    $a = LoyaltyAccount::factory()->create(['store_id' => $store->id, 'loyalty_program_id' => $program->id, 'stamp_count' => 2]);
    $program->update(['card_design' => 'simple', 'stamp_icon' => 'star']);
    $a->refresh();
    $simple = CoffeeCardSample::artwork($a, 'apple');
    $program->update(['stamp_icon' => 'heart']);
    $a->refresh();
    expect(CoffeeCardSample::artwork($a, 'apple'))->toBe($simple);
    $program->update(['card_design' => 'illustrated']);
    $a->refresh();
    $illustrated = CoffeeCardSample::artwork($a, 'apple');
    expect($illustrated)->not->toBe($simple);
    $program->update(['card_pattern' => 'waves']);
    $a->refresh();
    expect(CoffeeCardSample::artwork($a, 'apple'))->not->toBe($illustrated);
    foreach ([1, 6, 10, 20, 21, 1000] as $target) {
        $png = app(CardStyleArtwork::class)->render($target,1,true,'apple',CardDesign::options($program));
        expect(strlen($png))->toBeLessThan(500000);
    }
});
