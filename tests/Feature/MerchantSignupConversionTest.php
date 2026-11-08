<?php

use App\Models\User;
use App\Support\MerchantSignupAnalytics;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['marketing.ga_measurement_id' => 'G-LOCALTEST']);
    Mail::fake();
    Notification::fake();
    Queue::fake();
});

function conversionRegistration(array $changes = []): array
{
    return array_merge([
        'name' => 'TEST Conversion Owner', 'store_name' => 'TEST Conversion Cafe',
        'email' => 'conversion@example.invalid', 'password' => 'local-test-password',
        'password_confirmation' => 'local-test-password',
    ], $changes);
}

test('successful merchant registration emits one conversion and reload does not repeat it', function () {
    $this->get('/register?utm_source=sendgrid&utm_medium=email&utm_campaign=cafe_launch')->assertOk()
        ->assertDontSee('merchant-signup-conversion');
    $this->post('/register', conversionRegistration())->assertRedirect(route('verification.notice'));
    $owner = User::where('email', 'conversion@example.invalid')->sole();
    expect($owner->stores()->count())->toBe(1);
    expect($owner->email_verified_at)->toBeNull();

    $response = $this->get('/verify-email')->assertOk()->assertSee('merchant-signup-conversion')
        ->assertSee("'sign_up'", false)->assertSee('send_page_view')->assertSee('cafe_launch');
    preg_match('~<script id="merchant-signup-conversion">(.*?)</script>~s', $response->getContent(), $match);
    expect($match[1])->not->toContain($owner->email)->not->toContain($owner->name)
        ->not->toContain('TEST Conversion Cafe')->not->toContain('local-test-password');
    $this->get('/verify-email')->assertOk()->assertDontSee('merchant-signup-conversion');
});

test('invalid or duplicate registrations never count as new accounts', function () {
    $this->post('/register', conversionRegistration(['email' => 'invalid']))->assertSessionHasErrors('email');
    expect(session()->has('merchant_signup_completed'))->toBeFalse();
    User::factory()->create(['email' => 'conversion@example.invalid']);
    $this->post('/register', conversionRegistration())->assertSessionHasErrors('email');
    expect(session()->has('merchant_signup_completed'))->toBeFalse();
});

test('normal verification visits and resends do not count as registrations', function () {
    $owner = User::factory()->unverified()->create();
    $this->actingAs($owner)->get('/verify-email')->assertOk()->assertDontSee('merchant-signup-conversion');
    $this->post('/email/verification-notification')->assertRedirect();
    $this->get('/verify-email')->assertOk()->assertDontSee('merchant-signup-conversion');
});

test('conversion tracking can be disabled without breaking signup', function () {
    config(['marketing.ga_measurement_id' => '']);
    $this->post('/register', conversionRegistration())->assertRedirect(route('verification.notice'));
    $this->get('/verify-email')->assertOk()->assertDontSee('merchant-signup-conversion');
    expect(User::where('email', 'conversion@example.invalid')->count())->toBe(1);
});

test('analytics contains only validated campaign codes and a query-free page location', function () {
    $owner = User::factory()->create();
    $owner->forceFill(['signup_attribution' => [
        'utm_source' => 'sendgrid', 'utm_medium' => 'email', 'utm_campaign' => 'cafe_launch',
        'utm_content' => 'owner@example.invalid', 'utm_term' => '</script><script>alert(1)</script>',
        'secret_token' => 'private-token',
    ]]);
    $options = MerchantSignupAnalytics::configuration($owner)['options'];
    expect($options)->toMatchArray([
        'campaign_source' => 'sendgrid', 'campaign_medium' => 'email', 'campaign_name' => 'cafe_launch',
        'send_page_view' => false, 'page_referrer' => '', 'page_location' => url('/verify-email'),
    ])->not->toHaveKey('campaign_content')->not->toHaveKey('campaign_term')->not->toHaveKey('secret_token');
    config(['marketing.ga_measurement_id' => 'G-INVALID"</script>']);
    expect(MerchantSignupAnalytics::configuration($owner))->toBeNull();
});
