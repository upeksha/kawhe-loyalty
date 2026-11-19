<?php

use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Services\Wallet\Artwork\CardStyleArtwork;
use App\Support\CardDesign;
use App\Support\CoffeeCardSample;
use App\Support\StoreAssets;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('Photo browser reward artwork overlays the existing photo without embedding it again', function (string $icon) {
    config(['app.env' => 'testing', 'app.url' => 'https://testing.kawhe.shop', 'filesystems.assets_disk' => 'public']);
    Storage::fake('public'); Queue::fake();
    $store = Store::factory()->create();
    $program = $store->resolvedDefaultProgram();
    $photo = UploadedFile::fake()->image('cafe.jpg', 600, 400);
    $upload = UploadedFile::fake()->image('reward.png', 96, 96);
    StoreAssets::put('card-designs/reward-photo.jpg', file_get_contents($photo->getRealPath()));
    StoreAssets::put('card-designs/reward-upload.png', file_get_contents($upload->getRealPath()));
    $program->update(['card_design' => 'photo', 'card_photo_path' => 'card-designs/reward-photo.jpg',
        'stamp_icon' => 'heart', 'stamp_shape' => 'rounded', 'stamp_color' => '#FFD166',
        'reward_icon' => $icon, 'reward_icon_path' => 'card-designs/reward-upload.png']);
    $account = LoyaltyAccount::factory()->create(['store_id' => $store->id, 'loyalty_program_id' => $program->id, 'stamp_count' => 0, 'reward_balance' => 1]);
    $options = CardDesign::options($program);
    $originalHash = substr(hash('sha256', serialize([CardStyleArtwork::RENDERER_VERSION, $options])), 0, 24);
    $webPath = CoffeeCardSample::artwork($account, 'web');
    expect($webPath)->not->toContain('/'.$originalHash.'/');
    $web = imagecreatefromstring(StoreAssets::get($webPath));
    expect(imagecolorsforindex($web, imagecolorat($web, 0, 0))['alpha'])->toBe(127)
        ->and(imagecolorsforindex($web, imagecolorat($web, 719, 469))['alpha'])->toBe(127)
        ->and(imagecolorsforindex($web, imagecolorat($web, 360, 235))['alpha'])->toBeLessThan(127);
    imagedestroy($web);
    foreach (['apple', 'google'] as $platform) {
        $path = CoffeeCardSample::artwork($account, $platform);
        expect($path)->toContain('/'.$originalHash.'/')
            ->and(StoreAssets::get($path))->toBe(app(CardStyleArtwork::class)->render((int) $account->reward_target, 0, true, $platform, $options));
    }
    $this->get(route('card.show', $account->public_token))->assertOk()->assertSee('id="photo-reward-art"', false);
    $json = $this->getJson('/api/card/'.$account->public_token)->assertOk()->json();
    expect($json['coffee_artwork_url'])->toBe(StoreAssets::url($webPath));
    $account->update(['stamp_count' => 4, 'reward_balance' => 0]);
    $collectingPath = CoffeeCardSample::artwork($account, 'web');
    expect($collectingPath)->toContain('/'.$originalHash.'/')
        ->and(StoreAssets::get($collectingPath))->toBe(app(CardStyleArtwork::class)->render((int) $account->reward_target, 4, false, 'web', $options));
})->with(['match', 'gift', 'star', 'custom']);
