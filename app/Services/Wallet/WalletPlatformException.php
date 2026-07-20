<?php

namespace App\Services\Wallet;

use RuntimeException;

class WalletPlatformException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $category = 'unknown',
        public readonly bool $retryable = false,
        public readonly ?int $httpStatus = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus ?? 0, $previous);
    }
}
