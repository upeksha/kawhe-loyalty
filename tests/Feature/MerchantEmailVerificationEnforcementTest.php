<?php

use App\Models\User;
use App\Notifications\MerchantVerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Notification::fake();
    $this->merchant = User::factory()->unverified()->create();
});

test('unverified merchants cannot enter protected web areas', function (string $route) {
    $this->actingAs($this->merchant)->get(route($route))->assertRedirect(route('verification.notice'));
})->with(['dashboard', 'merchant.dashboard', 'merchant.stores.index', 'merchant.scanner', 'merchant.onboarding.wizard.store-basics']);

test('unverified administrator is also required to verify', function () {
    $this->merchant->update(['is_super_admin' => true]);
    $this->actingAs($this->merchant)->get(route('admin.dashboard'))->assertRedirect(route('verification.notice'));
});

test('profile billing and verification stay accessible to unverified accounts', function () {
    $this->actingAs($this->merchant)->get('/profile')->assertOk();
    $this->get('/billing')->assertOk();
    $this->get('/verify-email')->assertOk()->assertSee('Change email or manage account');
    $this->post('/billing/checkout')->assertRedirect(route('verification.notice'));
});

test('API rejects unverified store and scanner access even with an existing bearer token', function () {
    $token = $this->merchant->createToken('existing-mobile')->plainTextToken;
    $this->withToken($token)->getJson('/api/v1/stores')->assertForbidden();
    foreach (['scanner/preview', 'stamp', 'redeem/info', 'redeem'] as $path) {
        $this->withToken($token)->postJson('/api/v1/'.$path, [])->assertForbidden();
    }
    $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('user.email_verified_at', null);
});

test('registration queues verification and requires proof before onboarding', function () {
    $this->post('/register', ['name' => 'New owner', 'email' => 'new-owner@example.com', 'store_name' => 'Cafe', 'password' => 'password', 'password_confirmation' => 'password'])
        ->assertRedirect(route('verification.notice'));
    $user = User::where('email', 'new-owner@example.com')->firstOrFail();
    Notification::assertSentTo($user, MerchantVerifyEmail::class);
    expect($user->hasVerifiedEmail())->toBeFalse();
});

test('changing email revokes verification and queues a link to the replacement address', function () {
    $this->merchant->markEmailAsVerified();
    $this->actingAs($this->merchant)->patch('/profile', ['name' => $this->merchant->name, 'email' => 'replacement@example.com'])
        ->assertRedirect('/profile');
    expect($this->merchant->fresh()->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($this->merchant, MerchantVerifyEmail::class, fn ($notification) => $notification->email === 'replacement@example.com');
    $this->get('/dashboard')->assertRedirect(route('verification.notice'));
});

test('expired tampered and other-account verification links are rejected', function () {
    $valid = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $this->merchant->id, 'hash' => sha1($this->merchant->email)], absolute: false);
    $expired = URL::temporarySignedRoute('verification.verify', now()->subMinute(), ['id' => $this->merchant->id, 'hash' => sha1($this->merchant->email)], absolute: false);
    $this->actingAs($this->merchant)->get($expired)->assertForbidden();
    $this->get($valid.'&tampered=yes')->assertForbidden();
    $other = User::factory()->unverified()->create();
    $this->actingAs($other)->get($valid)->assertForbidden();
    expect($this->merchant->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('queued verification link uses configured origin and works only for the dispatched email', function () {
    config(['app.url' => 'https://testing.kawhe.shop']);
    $notification = new MerchantVerifyEmail($this->merchant->email);
    $url = $notification->toMail($this->merchant)->actionUrl;
    expect($url)->toStartWith('https://testing.kawhe.shop/verify-email/');
    $this->actingAs($this->merchant)->get($url)->assertOk();
    expect($this->merchant->fresh()->hasVerifiedEmail())->toBeFalse();
    $this->post($url)->assertRedirect();
    expect($this->merchant->fresh()->hasVerifiedEmail())->toBeTrue();
    $this->merchant->refresh();
    expect($notification->shouldSend($this->merchant, 'mail'))->toBeFalse();
    $this->merchant->update(['email' => 'different@example.com', 'email_verified_at' => null]);
    expect($notification->shouldSend($this->merchant, 'mail'))->toBeFalse();
    $this->get($url)->assertForbidden();
});

test('verification resends are throttled', function () {
    $this->actingAs($this->merchant);
    foreach (range(1, 6) as $attempt) {
        $this->post(route('verification.send'))->assertSessionHas('status', 'verification-link-sent');
    }
    $this->post(route('verification.send'))->assertStatus(429);
    Notification::assertSentToTimes($this->merchant, MerchantVerifyEmail::class, 6);
});

test('verified merchants are not sent another verification email', function () {
    $this->merchant->markEmailAsVerified();
    $this->actingAs($this->merchant)->post(route('verification.send'))->assertRedirect();
    Notification::assertNothingSent();
});

test('queue failure shows a retry message and leaves the merchant unverified', function () {
    Notification::shouldReceive('send')->andThrow(new \RuntimeException('Queue unavailable'));
    $this->actingAs($this->merchant)->from('/verify-email')->post(route('verification.send'))
        ->assertRedirect('/verify-email')->assertSessionHasErrors('verification');
    expect($this->merchant->fresh()->hasVerifiedEmail())->toBeFalse();
});
