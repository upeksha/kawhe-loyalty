<?php

namespace App\Services\Wallet\Artwork;

class GoogleWalletArtworkRenderer
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
                'program_logo' => $this->renderer->contain($logo, 660, 660, 0.15, 'center'),
                'hero' => $this->renderer->cover($hero, 1032, 812),
            ];
        } finally {
            $logo->destroy();
            $hero->destroy();
        }
    }
}
