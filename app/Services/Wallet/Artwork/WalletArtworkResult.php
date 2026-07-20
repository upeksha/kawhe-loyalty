<?php

namespace App\Services\Wallet\Artwork;

class WalletArtworkResult
{
    public function __construct(
        public readonly bool $changed,
        public readonly string $designHash,
        public readonly int $designVersion,
        public readonly array $manifest,
    ) {}
}
