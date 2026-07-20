<?php

namespace App\Services\Wallet;

use App\Models\LoyaltyProgram;
use App\Models\Store;

class WalletPreviewDataFactory
{
    public function forProgram(LoyaltyProgram $program): array
    {
        $program->loadMissing('store');

        return $this->make(
            $program->store,
            $program->name,
            $program->reward_title,
            (int) $program->reward_target,
            $program->brand_color,
            $program->background_color,
            $program->walletAssetUrl('apple', 'logo') ?? $program->pass_logo_url,
            $program->walletAssetUrl('apple', 'strip_2x') ?? $program->pass_hero_image_url,
            $program->walletAssetUrl('google', 'program_logo') ?? $program->pass_logo_url,
            $program->walletAssetUrl('google', 'hero') ?? $program->pass_hero_image_url,
        );
    }

    public function forStore(Store $store): array
    {
        $program = $store->resolvedDefaultProgram();
        if ($program) {
            return $this->forProgram($program);
        }

        return $this->make(
            $store,
            $store->reward_title,
            $store->reward_title,
            (int) $store->reward_target,
            $store->brand_color,
            $store->background_color,
            $store->pass_logo_url,
            $store->pass_hero_image_url,
            $store->pass_logo_url,
            $store->pass_hero_image_url,
        );
    }

    private function make(Store $store, ?string $programName, ?string $rewardTitle, int $target, ?string $brandColor, ?string $backgroundColor, ?string $appleLogo, ?string $appleHero, ?string $googleLogo, ?string $googleHero): array
    {
        $target = max(1, $target);

        return [
            'store_name' => $store->name,
            'program_name' => trim((string) $programName) ?: 'Loyalty card',
            'reward_title' => trim((string) $rewardTitle) ?: 'Reward',
            'reward_target' => $target,
            'brand_color' => $brandColor ?: '#0EA5E9',
            'background_color' => $backgroundColor ?: '#1F2937',
            'apple_logo_url' => $appleLogo,
            'apple_hero_url' => $appleHero,
            'google_logo_url' => $googleLogo,
            'google_hero_url' => $googleHero,
            'example_customer' => 'Alex',
            'example_stamps' => min(4, max(0, $target - 1)),
            'example_rewards' => 0,
            'example_manual_code' => 'A4K9',
        ];
    }
}
