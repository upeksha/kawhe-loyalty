<?php

namespace App\Services\Wallet\Artwork;

use RuntimeException;

class WalletImageRenderer
{
    public function contain(DecodedWalletImage $source, int $width, int $height, float $paddingRatio = 0, string $alignment = 'center', float $maxUpscale = 2): string
    {
        $canvas = $this->transparentCanvas($width, $height);
        $paddingX = (int) round($width * $paddingRatio);
        $paddingY = (int) round($height * $paddingRatio);
        $availableWidth = max(1, $width - ($paddingX * 2));
        $availableHeight = max(1, $height - ($paddingY * 2));
        $scale = min($availableWidth / $source->width, $availableHeight / $source->height, $maxUpscale);
        $targetWidth = max(1, (int) round($source->width * $scale));
        $targetHeight = max(1, (int) round($source->height * $scale));
        $x = $alignment === 'left' ? $paddingX : (int) floor(($width - $targetWidth) / 2);
        $y = (int) floor(($height - $targetHeight) / 2);

        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $source->image, $x, $y, 0, 0, $targetWidth, $targetHeight, $source->width, $source->height);

        return $this->encodePng($canvas);
    }

    public function cover(DecodedWalletImage $source, int $width, int $height): string
    {
        $canvas = $this->transparentCanvas($width, $height);
        $scale = max($width / $source->width, $height / $source->height);
        $targetWidth = max(1, (int) ceil($source->width * $scale));
        $targetHeight = max(1, (int) ceil($source->height * $scale));
        $x = (int) floor(($width - $targetWidth) / 2);
        $y = (int) floor(($height - $targetHeight) / 2);

        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $source->image, $x, $y, 0, 0, $targetWidth, $targetHeight, $source->width, $source->height);

        return $this->encodePng($canvas);
    }

    private function transparentCanvas(int $width, int $height): \GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);
        if (! $canvas) {
            throw new RuntimeException('Unable to allocate a wallet artwork canvas.');
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);

        return $canvas;
    }

    private function encodePng(\GdImage $image): string
    {
        ob_start();
        $written = imagepng($image, null, 8);
        $contents = ob_get_clean();
        imagedestroy($image);

        if (! $written || ! is_string($contents)) {
            throw new RuntimeException('Unable to encode wallet artwork as PNG.');
        }

        return $contents;
    }
}
