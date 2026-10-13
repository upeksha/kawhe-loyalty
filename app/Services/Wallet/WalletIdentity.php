<?php

namespace App\Services\Wallet;

class WalletIdentity
{
    public static function namespace(): string
    {
        // Test-only override keeps legacy production fixtures explicit.
        if (app()->runningUnitTests() && config('wallet.test_identity_namespace') !== null) {
            return (string) config('wallet.test_identity_namespace');
        }

        $host = strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));
        if ($host === '') {
            throw new \RuntimeException('Wallet identity requires a configured application host.');
        }
        if ($host === 'app.kawhe.shop' && config('app.env') === 'production') {
            return '';
        }

        return 'env_'.substr(hash('sha256', $host), 0, 12);
    }

    public static function prefix(string $identifier): string
    {
        $namespace = self::namespace();

        return $namespace === '' ? $identifier : $namespace.'_'.$identifier;
    }
}
