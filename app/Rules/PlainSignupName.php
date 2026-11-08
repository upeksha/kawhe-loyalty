<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PlainSignupName implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        // Inspect encoded markup too, while preserving the customer's original name.
        $text = $value;
        for ($i = 0; $i < 3; $i++) {
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $hasMarkup = preg_match('/[<>\x00-\x1F\x7F]/u', $text);
        $hasLink = preg_match('~(?:https?://|ftp://|www\.|\b[\p{L}\p{N}-]+\.(?:com|net|org|co|nz|ru|io|info|biz|xyz|online|site|shop|app|me)(?:\b|/))~iu', $text);

        if ($hasMarkup || $hasLink) {
            $fail('Please enter a name without website links or HTML.');
        }
    }
}
