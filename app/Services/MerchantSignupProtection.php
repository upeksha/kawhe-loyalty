<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\IpUtils;

class MerchantSignupProtection
{
    public static function clientIp(Request $request): string
    {
        $peer = (string) $request->server('REMOTE_ADDR', 'unknown');
        $client = $request->header('CF-Connecting-IP');

        if (filter_var($peer, FILTER_VALIDATE_IP) && filter_var($client, FILTER_VALIDATE_IP)
            && IpUtils::checkIp($peer, config('merchant_signup.cloudflare_proxies', []))) {
            return $client;
        }

        // Ignore spoofable forwarded headers on direct-to-origin requests.
        return $peer;
    }

    public function verify(Request $request): void
    {
        // An optional field outside the visible form catches basic autofill bots.
        if ($request->input('company_website') !== null && $request->input('company_website') !== '') {
            $this->reject('honeypot');
        }

        if (! config('merchant_signup.turnstile_enabled')) {
            return;
        }

        $secret = config('merchant_signup.secret_key');
        $siteKey = config('merchant_signup.site_key');
        $hostname = config('merchant_signup.hostname');
        if (! $secret || ! $siteKey || ! $hostname || (app()->isProduction() && (
            preg_match('/^[123]x0{10,}/', $siteKey) || preg_match('/^[123]x0{10,}/', $secret)
        ))) {
            $this->reject('configuration', true);
        }

        $token = $request->input('cf-turnstile-response');
        if (! is_string($token) || trim($token) === '' || strlen($token) > 2048) {
            $this->reject('missing_token');
        }

        try {
            // No name, email, password or customer data is sent to this service.
            $response = Http::asForm()->connectTimeout(3)->timeout(8)->post(
                'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                ['secret' => $secret, 'response' => $token]
            );
        } catch (ConnectionException $e) {
            $this->reject('unavailable', true);
        }

        if (! $response->successful()) {
            $this->reject('unavailable', true);
        }

        if ($response->json('success') !== true
            || $response->json('hostname') !== $hostname
            || $response->json('action') !== config('merchant_signup.action')) {
            $this->reject('invalid_challenge');
        }
    }

    private function reject(string $reason, bool $unavailable = false): never
    {
        // Keep credentials, submitted names, email addresses and tokens out of logs.
        Log::notice('Merchant signup protection rejected a submission', ['reason' => $reason]);

        throw ValidationException::withMessages([
            'signup_verification' => $unavailable
                ? 'We could not check your signup right now. Please try again in a moment.'
                : 'Please complete the security check and try again.',
        ]);
    }
}
