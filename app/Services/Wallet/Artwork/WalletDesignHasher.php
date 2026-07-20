<?php

namespace App\Services\Wallet\Artwork;

use App\Models\LoyaltyProgram;

class WalletDesignHasher
{
    public function hash(LoyaltyProgram $program, string $logoSourceHash, string $heroSourceHash): string
    {
        $program->loadMissing('store');

        $payload = [
            'renderer_version' => WalletArtworkService::RENDERER_VERSION,
            'program_name' => trim((string) $program->name),
            'store_name' => trim((string) $program->store?->name),
            'reward_title' => trim((string) $program->reward_title),
            'reward_target' => max(1, (int) $program->reward_target),
            'brand_color' => strtoupper((string) $program->brand_color),
            'background_color' => strtoupper((string) $program->background_color),
            'logo_source_hash' => $logoSourceHash,
            'hero_source_hash' => $heroSourceHash,
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
