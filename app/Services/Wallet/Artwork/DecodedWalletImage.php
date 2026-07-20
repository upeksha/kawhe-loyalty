<?php

namespace App\Services\Wallet\Artwork;

use GdImage;

class DecodedWalletImage
{
    public function __construct(
        public GdImage $image,
        public readonly int $width,
        public readonly int $height,
        public readonly string $mimeType,
        public readonly bool $hasAlpha,
    ) {}

    public function destroy(): void
    {
        if (isset($this->image)) {
            imagedestroy($this->image);
            unset($this->image);
        }
    }
}
