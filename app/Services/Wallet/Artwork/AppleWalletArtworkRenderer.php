<?php

namespace App\Services\Wallet\Artwork;

class AppleWalletArtworkRenderer
{
    public function __construct(
        private readonly WalletImageDecoder $decoder,
        private readonly WalletImageRenderer $renderer,
    ) {}

    public function render(string $logoContents, string $heroContents): array
    {
        $logo = $this->decoder->decode($logoContents);
        $hero = $this->decoder->decode($heroContents);

        try {
            return [
                'logo' => $this->renderer->contain($logo, 160, 50, 0.06, 'left'),
                'logo_2x' => $this->renderer->contain($logo, 320, 100, 0.06, 'left'),
                'logo_3x' => $this->renderer->contain($logo, 480, 150, 0.06, 'left'),
                'strip' => $this->renderer->cover($hero, 375, 144),
                'strip_2x' => $this->renderer->cover($hero, 750, 288),
                'strip_3x' => $this->renderer->cover($hero, 1125, 432),
            ];
        } finally {
            $logo->destroy();
            $hero->destroy();
        }
    }
}
