<?php

use App\Models\Store;
use App\Services\Wallet\AppleWalletPassService;

it('renders abstract apple wallet pattern assets at expected wallet sizes', function () {
    if (! function_exists('imagecreatetruecolor')) {
        $this->markTestSkipped('GD extension is required for Apple Wallet image rendering.');
    }

    $service = new AppleWalletPassService();
    $method = new ReflectionMethod($service, 'createAbstractPatternPng');
    $method->setAccessible(true);

    $assets = [
        'strip.png' => [375, 123],
        'strip@2x.png' => [750, 246],
        'background.png' => [180, 220],
        'background@2x.png' => [360, 440],
    ];

    foreach ($assets as $name => [$width, $height]) {
        $path = storage_path('framework/testing/'.$name);
        @mkdir(dirname($path), 0755, true);
        @unlink($path);

        expect($method->invoke(
            $service,
            $path,
            $width,
            $height,
            '#562300',
            '#d6a24a',
            Store::WALLET_BACKGROUND_PATTERN_ORGANIC
        ))->toBeTrue();

        expect(getimagesize($path))->toMatchArray([$width, $height]);
        expect(countSampledColors($path))->toBeGreaterThan(3);

        @unlink($path);
    }
});

function countSampledColors(string $path): int
{
    $image = imagecreatefrompng($path);
    $width = imagesx($image);
    $height = imagesy($image);
    $colors = [];

    for ($x = 0; $x < $width; $x += max(1, (int) floor($width / 8))) {
        for ($y = 0; $y < $height; $y += max(1, (int) floor($height / 8))) {
            $colors[imagecolorat($image, $x, $y)] = true;
        }
    }

    imagedestroy($image);

    return count($colors);
}
