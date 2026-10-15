<?php

namespace App\Support;

use App\Models\LoyaltyAccount;

/** Isolated, eight-stamp design trial; never enabled for production or other programs. */
class CoffeeCardSample
{
    public static function enabled(LoyaltyAccount $account): bool
    {
        return config('app.env') === 'testing'
            && parse_url(config('app.url'), PHP_URL_HOST) === 'testing.kawhe.shop'
            && $account->resolvedProgram()?->slug === 'kawhe-illustrated-wallet-qa'
            && (int) $account->resolvedProgram()->reward_target === 8;
    }

    public static function artwork(LoyaltyAccount $account, string $platform): string
    {
        if (! in_array($platform, ['web', 'apple', 'google'], true)) {
            throw new \InvalidArgumentException('Unsupported sample platform.');
        }
        $count = max(0, min(8, (int) $account->stamp_count));
        $state = $account->reward_balance > 0 && $count === 0 ? 'ready' : (string) $count;

        return "images/coffee-sample-v1/{$platform}-{$state}.png";
    }
}
