<?php

namespace App\Support;

use App\Models\LoyaltyAccount;

/** Shared card-style rendering for explicitly selected loyalty programs. */
class CoffeeCardSample
{
    public static function available(): bool
    {
        return in_array([config('app.env'), parse_url(config('app.url'), PHP_URL_HOST)], [
            ['testing', 'testing.kawhe.shop'],
            ['production', 'app.kawhe.shop'],
        ], true);
    }

    public static function rules($model = null): array
    {
        $styles = self::available()
            ? ['simple', 'illustrated', 'photo', 'illustrated_coffee']
            : ['classic'];

        // Classic is no longer offered for new cards. Keep accepting it only
        // when editing a legacy Classic record restored from an older release.
        if ($model?->card_design === 'classic' && ! in_array('classic', $styles, true)) {
            $styles[] = 'classic';
        }

        return ['sometimes', 'required', \Illuminate\Validation\Rule::in($styles)];
    }

    public static function programEnabled($program): bool
    {
        return in_array($program?->card_design, ['simple', 'illustrated', 'photo', 'illustrated_coffee'], true) && self::available();
    }

    public static function enabled(LoyaltyAccount $account): bool
    {
        return self::programEnabled($account->resolvedProgram());
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

        $program = $account->resolvedProgram();
        if ($program->card_design !== 'illustrated_coffee') {
            $options = CardDesign::options($program);
            $hash = substr(hash('sha256', serialize($options)), 0, 24);
            // Account-scoped cache also covers old/replaced photos at deletion.
            $ownerId = $account->store->user_id;
            $path = "wallet/card-styles-v2/accounts/{$ownerId}/{$hash}/{$platform}-{$target}-{$state}.png";
            if (! StoreAssets::exists($path)) {
                StoreAssets::put($path, app(\App\Services\Wallet\Artwork\CardStyleArtwork::class)->render($target, $count, $account->reward_balance > 0, $platform, $options));
            }

            return $path;
        }
        $path = "wallet/coffee-sample-v2/{$platform}-{$target}-{$state}.png";
        if (! StoreAssets::exists($path)) {
            StoreAssets::put($path, app(\App\Services\Wallet\Artwork\CoffeeStampArtwork::class)
                ->render($target, $count, $account->reward_balance > 0, $platform));
        }

        return $path;
    }
}
