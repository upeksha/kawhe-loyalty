<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\MerchantVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MerchantSignupProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Mail::fake();
        Notification::fake();
        Http::preventStrayRequests();
        config([
            'merchant_signup.turnstile_enabled' => true,
            'merchant_signup.site_key' => 'test-site-key',
            'merchant_signup.secret_key' => 'test-secret-key',
            'merchant_signup.hostname' => 'app.kawhe.shop',
        ]);
    }

    private function signup(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Hēmi O’Connor-Smith',
            'store_name' => 'Tāmaki Café & Co.',
            'email' => 'owner@example.invalid',
            'password' => 'test-password-only',
            'password_confirmation' => 'test-password-only',
            'company_website' => '',
            'cf-turnstile-response' => 'test-token',
        ], $overrides);
    }

    private function assertNoSignup(): void
    {
        $this->assertGuest();
        foreach (['users', 'stores', 'loyalty_programs', 'merchant_signup_alerts'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        Mail::assertNothingOutgoing();
        Notification::assertNothingSent();
    }

    public function test_valid_challenge_preserves_names_and_verification_before_welcome(): void
    {
        Http::fake(['challenges.cloudflare.com/*' => Http::response([
            'success' => true, 'hostname' => 'app.kawhe.shop', 'action' => 'merchant_signup',
        ])]);
        $this->post('/register', $this->signup())->assertRedirect(route('verification.notice'));
        $user = User::sole();
        $this->assertSame('Hēmi O’Connor-Smith', $user->name);
        $this->assertSame('Tāmaki Café & Co.', $user->stores()->sole()->name);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->merchant_welcome_queued_at);
        Notification::assertSentTo($user, MerchantVerifyEmail::class);
        Mail::assertNotQueued(\App\Mail\MerchantWelcomeEmail::class);
        Mail::assertNotSent(\App\Mail\MerchantWelcomeEmail::class);
        $this->assertDatabaseCount('merchant_signup_alerts', 1);
        Http::assertSent(fn ($request) => $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
            && $request['secret'] === 'test-secret-key' && $request['response'] === 'test-token'
            && ! isset($request['email']) && ! isset($request['password']) && ! isset($request['name']));
    }

    public function test_missing_array_and_oversized_tokens_create_nothing(): void
    {
        foreach (['', ['unexpected'], str_repeat('x', 2049)] as $token) {
            $this->post('/register', $this->signup(['cf-turnstile-response' => $token]))
                ->assertSessionHasErrors('signup_verification');
        }
        Http::assertNothingSent();
        $this->assertNoSignup();
    }

    public function test_honeypot_creates_nothing_even_when_turnstile_is_disabled(): void
    {
        config(['merchant_signup.turnstile_enabled' => false]);
        $this->post('/register', $this->signup(['company_website' => 'https://spam.invalid']))
            ->assertSessionHasErrors('signup_verification');
        Http::assertNothingSent();
        $this->assertNoSignup();
    }

    public function test_html_encoded_html_and_links_in_names_are_rejected(): void
    {
        foreach ([
            ['name' => '<a href="https://spam.invalid">payment available</a>'],
            ['store_name' => '&lt;a href=&quot;https://spam.invalid&quot;&gt;Offer&lt;/a&gt;'],
            ['name' => 'www.spam.invalid'],
            ['store_name' => 'jetroom.ru'],
        ] as $input) {
            $this->post('/register', $this->signup($input))->assertSessionHasErrors(array_keys($input));
        }
        Http::assertNothingSent();
        $this->assertNoSignup();
    }

    public function test_invalid_expired_and_replayed_challenges_create_nothing(): void
    {
        Http::fake(['challenges.cloudflare.com/*' => Http::response([
            'success' => false, 'error-codes' => ['timeout-or-duplicate'],
        ])]);
        $this->post('/register', $this->signup())->assertSessionHasErrors('signup_verification');
        $this->assertNoSignup();
    }

    public function test_other_hostname_or_action_cannot_register(): void
    {
        foreach ([
            ['success' => true, 'hostname' => 'other.kawhe.shop', 'action' => 'merchant_signup'],
            ['success' => true, 'hostname' => 'app.kawhe.shop', 'action' => 'customer_join'],
            ['success' => 'true', 'hostname' => 'app.kawhe.shop', 'action' => 'merchant_signup'],
        ] as $body) {
            Http::fake(['challenges.cloudflare.com/*' => Http::response($body)]);
            $this->post('/register', $this->signup())->assertSessionHasErrors('signup_verification');
        }
        $this->assertNoSignup();
    }

    public function test_provider_failure_fails_closed_with_retry_message(): void
    {
        Http::fake(['challenges.cloudflare.com/*' => Http::failedConnection()]);
        $this->post('/register', $this->signup())->assertSessionHasErrors([
            'signup_verification' => 'We could not check your signup right now. Please try again in a moment.',
        ]);
        $this->assertNoSignup();
    }

    public function test_provider_http_error_and_malformed_response_fail_closed(): void
    {
        foreach ([Http::response('Unavailable', 503), Http::response('invalid-json', 200)] as $response) {
            Http::fake(['challenges.cloudflare.com/*' => $response]);
            $this->post('/register', $this->signup())->assertSessionHasErrors('signup_verification');
        }
        $this->assertNoSignup();
    }

    public function test_missing_configuration_does_not_bypass_protection(): void
    {
        config(['merchant_signup.secret_key' => null]);
        $this->post('/register', $this->signup())->assertSessionHasErrors('signup_verification');
        Http::assertNothingSent();
        $this->assertNoSignup();
    }

    public function test_official_test_keys_are_rejected_in_production(): void
    {
        $this->app->instance('env', 'production');
        config(['merchant_signup.site_key' => '1x00000000000000000000AA']);
        try {
            app(\App\Services\MerchantSignupProtection::class)->verify(
                \Illuminate\Http\Request::create('/register', 'POST', $this->signup())
            );
            $this->fail('Production must reject official test keys.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('signup_verification', $e->errors());
        }
        Http::assertNothingSent();
        $this->assertNoSignup();
    }

    public function test_ip_rate_limit_prevents_more_attempts_before_provider_or_email(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', $this->signup(['cf-turnstile-response' => '']))->assertRedirect();
        }
        $this->post('/register', $this->signup())->assertStatus(429)->assertHeader('Retry-After');
        Http::assertNothingSent();
        $this->assertNoSignup();
    }

    public function test_hourly_ip_limit_cannot_be_bypassed_by_spacing_bursts(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->post('/register', $this->signup(['email' => "owner-$i@example.invalid", 'cf-turnstile-response' => '']))->assertRedirect();
            $this->travel(61)->seconds();
        }
        $this->post('/register', $this->signup())->assertStatus(429);
        $this->assertNoSignup();
    }

    public function test_email_limit_cannot_be_bypassed_by_changing_ip(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "192.0.2.$i"])
                ->post('/register', $this->signup(['cf-turnstile-response' => '']))->assertRedirect();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.11'])
            ->post('/register', $this->signup())->assertStatus(429);
        $this->assertNoSignup();
    }

    public function test_only_merchant_registration_renders_challenge_and_honeypot(): void
    {
        $this->get('/register')->assertOk()->assertSee('cf-turnstile')->assertSee('company_website');
        $this->get('/login')->assertOk()->assertDontSee('cf-turnstile');
        $this->assertSame(['web', 'guest', 'throttle:merchant-signup'],
            collect(app('router')->getRoutes())->first(fn ($r) => $r->uri() === 'register' && in_array('POST', $r->methods()))->gatherMiddleware());
    }

    public function test_cloudflare_visitors_do_not_share_the_proxy_rate_limit(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '173.245.48.1'])
                ->withHeader('CF-Connecting-IP', "192.0.2.$i")
                ->post('/register', $this->signup(['cf-turnstile-response' => '']))->assertRedirect();
        }
        $this->assertNoSignup();
    }

    public function test_direct_requests_cannot_spoof_forwarded_ips_to_evade_limits(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])
                ->withHeaders(['CF-Connecting-IP' => "198.51.100.$i", 'X-Forwarded-For' => "198.51.100.$i"])
                ->post('/register', $this->signup(['cf-turnstile-response' => '']))->assertRedirect();
        }
        $this->withHeaders(['CF-Connecting-IP' => '198.51.100.6', 'X-Forwarded-For' => '198.51.100.6'])
            ->post('/register', $this->signup())->assertStatus(429);
        $this->assertNoSignup();
    }
}
