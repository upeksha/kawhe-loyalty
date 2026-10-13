<?php

use App\Mail\VerifyCustomerEmail;
use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;
use App\Notifications\MerchantResetPassword;
use App\Services\Customers\CardRecoveryService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;

test('customer verification queue failure is not reported as delivery and permits retry', function () {
    $account = LoyaltyAccount::factory()->create(['verified_at' => null]);
    $mailManager = Mail::getFacadeRoot();
    Mail::shouldReceive('to')->once()->andReturnSelf();
    Mail::shouldReceive('queue')->once()->andThrow(new RuntimeException('Queue unavailable'));
    $this->postJson(route('customer.email.verification.send', $account->public_token))
        ->assertStatus(503)->assertJson(['success' => false]);
    expect($account->fresh()->email_verification_sent_at)->toBeNull();
    expect($account->fresh()->email_verification_token_hash)->toBeNull();
    Mail::swap($mailManager);
    Mail::fake();
    $this->postJson(route('customer.email.verification.send', $account->public_token))->assertOk();
    Mail::assertQueued(VerifyCustomerEmail::class);
});

test('reset emails are queued with retries and a trusted testing origin', function () {
    config(['app.url' => 'https://testing.kawhe.shop']);
    Notification::fake();
    $user = User::factory()->create();
    $this->withServerVariables(['HTTP_HOST' => 'untrusted.example'])->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors();
    Notification::assertSentTo($user, MerchantResetPassword::class, function ($notification) use ($user) {
        expect($notification->queue)->toBe('emails');
        expect($notification->tries)->toBe(3);
        expect($notification->toMail($user)->actionUrl)->toStartWith('https://testing.kawhe.shop/reset-password/');
        expect($notification->shouldSend($user, 'mail'))->toBeTrue();
        Password::deleteToken($user);
        expect($notification->shouldSend($user, 'mail'))->toBeFalse();

        return true;
    });
});

test('delayed reset jobs do not send for a changed address or expired token', function () {
    $user = User::factory()->create();
    $mail = new MerchantResetPassword(Password::createToken($user), $user->email);
    $user->email = 'replacement@example.test';
    expect($mail->shouldSend($user, 'mail'))->toBeFalse();
    $user->refresh();
    $this->travel(config('auth.passwords.users.expire') + 1)->minutes();
    expect($mail->shouldSend($user, 'mail'))->toBeFalse();
});

test('password reset queue outages return a recoverable error', function () {
    Password::shouldReceive('sendResetLink')->once()->andThrow(new RuntimeException('Queue unavailable'));
    $this->post('/forgot-password', ['email' => 'test@example.test'])->assertRedirect()->assertSessionHasErrors('email');
});

test('card recovery queue failure releases the recipient resend allowance', function () {
    $store = Store::factory()->create();
    $program = $store->ensureDefaultProgramExists();
    $customer = Customer::factory()->create();
    Mail::shouldReceive('to')->once()->andReturnSelf();
    Mail::shouldReceive('queue')->once()->andThrow(new RuntimeException('Queue unavailable'));
    app(CardRecoveryService::class)->send($customer, $program);
    expect(RateLimiter::attempts('card-recovery:'.hash('sha256', strtolower($customer->email))))->toBe(0);
    $this->assertDatabaseCount('customer_card_recoveries', 0);
});

test('legacy customer verification queues expiring links on the trusted origin', function () {
    config(['app.url' => 'https://testing.kawhe.shop']);
    $account = LoyaltyAccount::factory()->create(['verified_at' => null]);
    $mail = new \App\Notifications\VerifyLoyaltyAccount($account);
    expect($mail->queue)->toBe('emails');
    $url = $mail->toMail($account)->actionUrl;
    expect($url)->toStartWith('https://testing.kawhe.shop/c/')->toContain('expires=');
    expect($mail->shouldSend($account, 'mail'))->toBeTrue();
    $account->customer->email = 'replacement@example.test';
    expect($mail->shouldSend($account, 'mail'))->toBeFalse();
});
