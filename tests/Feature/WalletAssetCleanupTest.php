<?php

use App\Models\Store;
use App\Models\User;
use App\Services\Wallet\Artwork\WalletArtworkService;
use App\Support\StoreAssets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('cleanup retains current and previous derivatives and original uploads', function () {
    Storage::fake('public');
    config(['filesystems.assets_disk' => 'public']);

    $makePng = function (array $rgb): string {
        $image = imagecreatetruecolor(900, 500);
        $color = imagecolorallocate($image, ...$rgb);
        imagefill($image, 0, 0, $color);
        ob_start();
        imagepng($image);
        $contents = ob_get_clean();
        imagedestroy($image);

        return $contents;
    };

    StoreAssets::put('pass-logos/cleanup-logo.png', $makePng([90, 30, 20]));
    StoreAssets::put('pass-heroes/cleanup-hero.png', $makePng([20, 80, 50]));
    $store = Store::factory()->create(['user_id' => User::factory()]);
    $program = $store->resolvedDefaultProgram();
    $program->update([
        'pass_logo_path' => 'pass-logos/cleanup-logo.png',
        'pass_hero_image_path' => 'pass-heroes/cleanup-hero.png',
        'background_color' => '#1F3B2C',
        'brand_color' => '#D6A24A',
    ]);
    $service = app(WalletArtworkService::class);
    $first = $service->syncForProgram($program, false);

    $program->update(['background_color' => '#2B1E18']);
    $second = $service->syncForProgram($program->fresh(), false);
    $program->update(['background_color' => '#3A2A22']);
    $third = $service->syncForProgram($program->fresh(), false);

    $obsolete = $first->manifest['apple']['logo'];
    touch(Storage::disk('public')->path($obsolete), now()->subDays(10)->timestamp);

    $this->artisan('wallet:cleanup-assets --days=7')->assertSuccessful();

    Storage::disk('public')->assertMissing($obsolete);
    Storage::disk('public')->assertExists($second->manifest['apple']['logo']);
    Storage::disk('public')->assertExists($third->manifest['apple']['logo']);
    Storage::disk('public')->assertExists('pass-logos/cleanup-logo.png');
    Storage::disk('public')->assertExists('pass-heroes/cleanup-hero.png');
});
