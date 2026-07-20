<?php

use App\Services\Wallet\Artwork\WalletImageValidator;

function walletTestPng(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    $color = imagecolorallocate($image, 30, 80, 60);
    imagefill($image, 0, 0, $color);
    ob_start();
    imagepng($image);
    $contents = ob_get_clean();
    imagedestroy($image);

    return $contents;
}

function oversizedWalletPngHeader(int $width, int $height): string
{
    $signature = "\x89PNG\r\n\x1a\n";
    $data = pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0);
    $chunkType = 'IHDR';

    return $signature.pack('N', strlen($data)).$chunkType.$data.pack('N', crc32($chunkType.$data));
}

function walletTestImage(int $type): string
{
    $image = imagecreatetruecolor(800, 300);
    $color = imagecolorallocate($image, 40, 100, 70);
    imagefill($image, 0, 0, $color);
    ob_start();
    $type === IMAGETYPE_JPEG ? imagejpeg($image, null, 90) : imagewebp($image, null, 90);
    $contents = ob_get_clean();
    imagedestroy($image);

    return $contents;
}

test('valid current image formats inspect successfully and low resolution is a warning', function () {
    $result = app(WalletImageValidator::class)->inspectBinary(walletTestPng(160, 50), 'logo', 'image/png');

    expect($result->isValid)->toBeTrue()
        ->and($result->width)->toBe(160)
        ->and($result->height)->toBe(50)
        ->and($result->warnings)->not->toBeEmpty();
});

test('corrupt and MIME-spoofed wallet images fail hard validation', function () {
    $corrupt = app(WalletImageValidator::class)->inspectBinary('not-an-image', 'logo', 'image/png');
    $spoofed = app(WalletImageValidator::class)->inspectBinary(walletTestPng(400, 200), 'logo', 'application/pdf');
    $acceptedTypeMismatch = app(WalletImageValidator::class)->inspectBinary(walletTestImage(IMAGETYPE_JPEG), 'logo', 'image/png');

    expect($corrupt->isValid)->toBeFalse()
        ->and($spoofed->isValid)->toBeFalse()
        ->and($acceptedTypeMismatch->isValid)->toBeFalse();
});

test('JPEG and WebP wallet images remain accepted', function () {
    $jpeg = app(WalletImageValidator::class)->inspectBinary(walletTestImage(IMAGETYPE_JPEG), 'hero', 'image/jpeg');
    $webp = app(WalletImageValidator::class)->inspectBinary(walletTestImage(IMAGETYPE_WEBP), 'hero', 'image/webp');

    expect($jpeg->isValid)->toBeTrue()
        ->and($jpeg->mimeType)->toBe('image/jpeg')
        ->and($webp->isValid)->toBeTrue()
        ->and($webp->mimeType)->toBe('image/webp');
});

test('logos without transparent edge spacing receive a non-blocking warning', function () {
    $result = app(WalletImageValidator::class)->inspectBinary(walletTestPng(500, 200), 'logo', 'image/png');

    expect($result->isValid)->toBeTrue()
        ->and(implode(' ', $result->warnings))->toContain('transparent spacing');
});

test('excessive wallet image pixel dimensions fail before decoding', function () {
    $result = app(WalletImageValidator::class)->inspectBinary(oversizedWalletPngHeader(50_000, 50_000), 'hero', 'image/png');

    expect($result->isValid)->toBeFalse()
        ->and(implode(' ', $result->errors))->toContain('too large');
});
