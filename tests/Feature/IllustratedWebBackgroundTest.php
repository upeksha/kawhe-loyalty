<?php

use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Services\Wallet\Artwork\CardStyleArtwork;
use App\Support\CardDesign;
use App\Support\CoffeeCardSample;
use App\Support\StoreAssets;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('illustrated browser cards put selected artwork behind the full card with transparent foregrounds', function (string $pattern, bool $ready) {
    config(['app.env' => 'testing', 'app.url' => 'https://testing.kawhe.shop', 'filesystems.assets_disk' => 'public']);
    Storage::fake('public'); Queue::fake();
    $store = Store::factory()->create();
    $program = $store->resolvedDefaultProgram();
    $program->update(['card_design' => 'illustrated', 'card_pattern' => $pattern, 'background_color' => '#8A2453',
        'card_text_color' => '#FFF4CF', 'stamp_color' => '#FFD166', 'stamp_shape' => 'square', 'reward_icon' => 'star']);
    $account = LoyaltyAccount::factory()->create(['store_id' => $store->id, 'loyalty_program_id' => $program->id,
        'stamp_count' => $ready ? 0 : 4, 'reward_balance' => $ready ? 1 : 0]);
    $names = ['mountains' => 'organic', 'waves' => 'floral', 'botanical' => 'waves'];
    $this->get(route('card.show', $account->public_token))->assertOk()
        ->assertSee('.loyalty-card::before', false)->assertSee('inset:0;pointer-events:none', false)
        ->assertSee('images/card-patterns/'.$names[$pattern].'-mask.png', false)
        ->assertSee('id="coffee-sample-art"', false);
    $path = CoffeeCardSample::artwork($account, 'web');
    $image = imagecreatefromstring(StoreAssets::get($path));
    expect(imagecolorsforindex($image, imagecolorat($image, 0, 0))['alpha'])->toBe(127)
        ->and(imagecolorsforindex($image, imagecolorat($image, 719, 469))['alpha'])->toBe(127);
    imagedestroy($image);
    $options = CardDesign::options($program);
    $nativeHash = substr(hash('sha256', serialize([CardStyleArtwork::RENDERER_VERSION, $options])), 0, 24);
    expect($path)->not->toContain('/'.$nativeHash.'/');
    foreach (['apple', 'google'] as $platform) {
        $native = CoffeeCardSample::artwork($account, $platform);
        expect($native)->toContain('/'.$nativeHash.'/')
            ->and(StoreAssets::get($native))->toBe(app(CardStyleArtwork::class)->render((int) $account->reward_target, $ready ? 0 : 4, $ready, $platform, $options));
    }
    $api = $this->getJson('/api/card/'.$account->public_token)->assertOk()->json();
    expect($api['coffee_artwork_url'])->toBe(StoreAssets::url($path));
})->with(['mountains', 'waves', 'botanical'])->with([false, true]);
