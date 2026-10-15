<?php

namespace App\Support;

use App\Models\LoyaltyAccount;

/** Isolated coffee design trial; never enabled for production or other programs. */
class CoffeeCardSample
{
    public static function enabled(LoyaltyAccount $account): bool
    {
        return config('app.env') === 'testing'
            && parse_url(config('app.url'), PHP_URL_HOST) === 'testing.kawhe.shop'
            && $account->resolvedProgram()?->slug === 'kawhe-illustrated-wallet-qa';
    }

    public static function resizedPng(string $source, int $width): string
    {
        return self::resizedContents(file_get_contents($source), $width);
    }

    public static function resizedContents(string $contents, int $width): string
    {
        $image = imagecreatefromstring($contents);
        $resized = imagescale($image, $width);
        try {
            // GD otherwise writes transparent pixels as opaque black in the pass logo.
            imagesavealpha($resized, true);
            ob_start();
            imagepng($resized);

            return ob_get_clean();
        } finally {
            imagedestroy($image);
            imagedestroy($resized);
        }
    }

    public static function shortReward(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?: 'Your reward';

        return mb_strlen($value) > 24 ? mb_substr($value, 0, 23).'…' : $value;
    }

    public static function artwork(LoyaltyAccount $account, string $platform): string
    {
        if (! in_array($platform, ['web', 'apple', 'google'], true)) {
            throw new \InvalidArgumentException('Unsupported sample platform.');
        }
        $target = max(1, (int) $account->reward_target);
        $count = max(0, min($target, (int) $account->stamp_count));
        $state = $account->reward_balance > 0 && $count === 0 ? 'ready' : (string) $count;

        $path = "wallet/coffee-sample-v2/{$platform}-{$target}-{$state}.png";
        if (! StoreAssets::exists($path)) {
            StoreAssets::put($path, app(\App\Services\Wallet\Artwork\CoffeeStampArtwork::class)
                ->render($target, $count, $account->reward_balance > 0, $platform));
        }

        return $path;
    }
}
