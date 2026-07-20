<?php

namespace App\Services\Wallet;

class WalletFailureClassifier
{
    public function classify(\Throwable $exception, string $platform): WalletPlatformException
    {
        if ($exception instanceof WalletPlatformException) {
            return $exception;
        }

        $message = $exception->getMessage();
        $lower = strtolower($message);
        $status = $this->httpStatus($exception);

        if ($status === 429 || str_contains($lower, 'rate limit')) {
            return new WalletPlatformException('The wallet provider is rate limiting updates.', 'rate_limit', true, $status, $exception);
        }

        if (($status !== null && $status >= 500) || preg_match('/timeout|timed out|connection|could not resolve|temporar|curl error|network/', $lower)) {
            return new WalletPlatformException('The wallet provider is temporarily unavailable.', 'network', true, $status, $exception);
        }

        if (preg_match('/not configured|configuration incomplete|key path not configured|not found|not readable|credential|certificate/', $lower)) {
            $category = str_contains($lower, 'certificate') || $platform === 'apple' ? 'credentials' : 'configuration';

            return new WalletPlatformException('Wallet credentials or configuration need attention.', $category, false, $status, $exception);
        }

        return new WalletPlatformException(
            'The wallet update could not be completed.',
            $platform === 'apple' ? 'apple_push' : 'google_object',
            false,
            $status,
            $exception,
        );
    }

    private function httpStatus(\Throwable $exception): ?int
    {
        $code = (int) $exception->getCode();

        return $code >= 100 && $code <= 599 ? $code : null;
    }
}
