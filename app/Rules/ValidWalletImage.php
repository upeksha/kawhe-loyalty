<?php

namespace App\Rules;

use App\Services\Wallet\Artwork\WalletImageValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class ValidWalletImage implements ValidationRule
{
    public function __construct(private readonly string $purpose = 'image') {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $result = app(WalletImageValidator::class)->inspectUploadedFile($value, $this->purpose);
        foreach ($result->errors as $error) {
            $fail($error);
        }
    }
}
