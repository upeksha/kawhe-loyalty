<?php

namespace App\Support;

use App\Models\User;

class MerchantSignupAnalytics
{
    public static function configuration(User $user): ?array
    {
        $id = config('marketing.ga_measurement_id');
        if (! is_string($id) || ! preg_match('/\AG-[A-Z0-9]+\z/', $id)) {
            return null;
        }

        $options = [
            'send_page_view' => false,
            'cookie_domain' => 'auto',
            'allow_google_signals' => false,
            'allow_ad_personalization_signals' => false,
            'page_location' => url('/verify-email'),
            'page_referrer' => '',
            'page_title' => 'Cafe account created | Kawhe',
        ];
        foreach (['source', 'medium', 'campaign', 'content', 'term'] as $field) {
            $value = $user->signup_attribution['utm_'.$field] ?? null;
            // Campaign codes only; never forward free-form text or email addresses.
            if (is_string($value) && preg_match('/\A[a-zA-Z0-9_-]{1,120}\z/', $value)) {
                $options['campaign_'.($field === 'campaign' ? 'name' : $field)] = $value;
            }
        }

        return ['id' => $id, 'options' => $options];
    }
}
