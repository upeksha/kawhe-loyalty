<?php

use App\Jobs\RefreshProgramWalletsJob;
use App\Models\Store;
use App\Models\User;
use App\Services\Wallet\Artwork\WalletArtworkService;
use App\Support\StoreAssets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    config(['filesystems.assets_disk' => 'public']);

    $this->png = function (int $width, int $height, array $rgb = [180, 60, 30], bool $transparent = false): string {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $color = $transparent
            ? imagecolorallocatealpha($image, $rgb[0], $rgb[1], $rgb[2], 60)
            : imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]);
        imagefill($image, 0, 0, $color);
        ob_start();
        imagepng($image);
        $contents = ob_get_clean();
        imagedestroy($image);

        return $contents;
    };

    $this->programWithArtwork = function (int $logoWidth = 500, int $logoHeight = 500) {
        $store = Store::factory()->create([
            'user_id' => User::factory(),
            'name' => 'Renderer Test Cafe',
            'brand_color' => '#C96A3B',
            'background_color' => '#1F3B2C',
            'pass_logo_path' => 'pass-logos/master-logo.png',
            'pass_hero_image_path' => 'pass-heroes/master-hero.png',
        ]);
        $program = $store->resolvedDefaultProgram();
        $program->update([
            'name' => 'Coffee Card',
            'brand_color' => '#C96A3B',
            'background_color' => '#1F3B2C',
            'pass_logo_path' => 'pass-logos/master-logo.png',
            'pass_hero_image_path' => 'pass-heroes/master-hero.png',
        ]);
        StoreAssets::put('pass-logos/master-logo.png', ($this->png)($logoWidth, $logoHeight, [200, 40, 20], true));
        StoreAssets::put('pass-heroes/master-hero.png', ($this->png)(1200, 700, [20, 90, 120]));

        return $program->fresh('store');
    };
});

test('generates separate correctly sized Apple and Google derivatives without changing source uploads', function () {
    $program = ($this->programWithArtwork)();
    $sourceLogo = StoreAssets::get($program->pass_logo_path);
    $sourceHero = StoreAssets::get($program->pass_hero_image_path);

    $result = app(WalletArtworkService::class)->syncForProgram($program, false);

    expect($result->changed)->toBeTrue()
        ->and($result->manifest['renderer_version'])->toBe(WalletArtworkService::RENDERER_VERSION)
        ->and(StoreAssets::get($program->pass_logo_path))->toBe($sourceLogo)
        ->and(StoreAssets::get($program->pass_hero_image_path))->toBe($sourceHero);

    $expectedDimensions = [
        'apple.logo' => [160, 50],
        'apple.logo_2x' => [320, 100],
        'apple.logo_3x' => [480, 150],
        'apple.strip' => [375, 144],
        'apple.strip_2x' => [750, 288],
        'apple.strip_3x' => [1125, 432],
        'google.program_logo' => [660, 660],
        'google.hero' => [1032, 812],
    ];

    foreach ($expectedDimensions as $key => $dimensions) {
        $contents = StoreAssets::get(data_get($result->manifest, $key));
        expect($contents)->not->toBeNull();
        $info = getimagesizefromstring($contents);
        expect([$info[0], $info[1]])->toBe($dimensions);
    }

    expect(StoreAssets::get($result->manifest['apple']['logo']))
        ->not->toBe(StoreAssets::get($result->manifest['google']['program_logo']));
});

test('Apple square logo keeps visible corners and transparent rectangular canvas', function () {
    $program = ($this->programWithArtwork)();
    $result = app(WalletArtworkService::class)->syncForProgram($program, false);
    $image = imagecreatefromstring(StoreAssets::get($result->manifest['apple']['logo']));

    $inside = imagecolorsforindex($image, imagecolorat($image, 12, 5));
    $outside = imagecolorsforindex($image, imagecolorat($image, 120, 4));

    expect($inside['red'])->toBeGreaterThan(150)
        ->and($inside['alpha'])->toBeLessThan(127)
        ->and($outside['alpha'])->toBe(127);

    imagedestroy($image);
});

test('Google logo keeps a transparent safe area for the circular mask', function () {
    $program = ($this->programWithArtwork)(800, 200);
    $result = app(WalletArtworkService::class)->syncForProgram($program, false);
    $image = imagecreatefromstring(StoreAssets::get($result->manifest['google']['program_logo']));

    $corner = imagecolorsforindex($image, imagecolorat($image, 20, 20));
    $centre = imagecolorsforindex($image, imagecolorat($image, 330, 330));

    expect($corner['alpha'])->toBe(127)
        ->and($centre['alpha'])->toBeLessThan(127);

    imagedestroy($image);
});

test('Apple tall logo fits by height without cropping', function () {
    $program = ($this->programWithArtwork)(150, 800);
    $result = app(WalletArtworkService::class)->syncForProgram($program, false);
    $image = imagecreatefromstring(StoreAssets::get($result->manifest['apple']['logo']));
    $opaquePixels = [];

    for ($y = 0; $y < imagesy($image); $y++) {
        for ($x = 0; $x < imagesx($image); $x++) {
            $color = imagecolorsforindex($image, imagecolorat($image, $x, $y));
            if ($color['alpha'] < 127) {
                $opaquePixels[] = [$x, $y];
            }
        }
    }

    $xs = array_column($opaquePixels, 0);
    $ys = array_column($opaquePixels, 1);
    expect(max($ys) - min($ys))->toBeGreaterThan(35)
        ->and(max($xs) - min($xs))->toBeLessThan(15)
        ->and(min($ys))->toBeGreaterThan(0)
        ->and(max($ys))->toBeLessThan(49);

    imagedestroy($image);
});

test('missing merchant artwork uses generated fallback assets', function () {
    $program = ($this->programWithArtwork)();
    $program->update([
        'pass_logo_path' => 'pass-logos/missing.png',
        'pass_hero_image_path' => 'pass-heroes/missing.png',
    ]);

    $result = app(WalletArtworkService::class)->syncForProgram($program->fresh('store'), false);

    expect(StoreAssets::exists($result->manifest['apple']['logo']))->toBeTrue()
        ->and(StoreAssets::exists($result->manifest['apple']['strip']))->toBeTrue()
        ->and(StoreAssets::exists($result->manifest['google']['program_logo']))->toBeTrue()
        ->and(StoreAssets::exists($result->manifest['google']['hero']))->toBeTrue();
});

test('wallet design version changes only for effective wallet design changes', function () {
    Queue::fake();
    $program = ($this->programWithArtwork)();
    $service = app(WalletArtworkService::class);

    $first = $service->syncForProgram($program);
    $same = $service->syncForProgram($program->fresh());

    expect($first->designVersion)->toBe(1)
        ->and($same->changed)->toBeFalse()
        ->and($same->designVersion)->toBe(1);

    $program->update(['registration_form_config' => ['email' => ['enabled' => true, 'required' => true]]]);
    $unrelated = $service->syncForProgram($program->fresh());
    expect($unrelated->changed)->toBeFalse()
        ->and($unrelated->designVersion)->toBe(1);

    $oldLogoPath = $unrelated->manifest['apple']['logo'];
    $program->update(['background_color' => '#2B1E18']);
    $changed = $service->syncForProgram($program->fresh());

    expect($changed->changed)->toBeTrue()
        ->and($changed->designVersion)->toBe(2)
        ->and($changed->manifest['apple']['logo'])->not->toBe($oldLogoPath);
    Queue::assertPushed(RefreshProgramWalletsJob::class);
});
