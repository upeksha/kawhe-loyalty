<?php

use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

function expiredMerchantLink($user)
{
    return URL::temporarySignedRoute('verification.verify', now()->subMinute(), ['id' => $user->id, 'hash' => sha1($user->email)], absolute: false);
}

test('expired verification GET and POST show recovery without verifying or sending email', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();
    $link = expiredMerchantLink($user);
    foreach (['get', 'post'] as $method) {
        $this->actingAs($user)->$method($link)->assertForbidden()
            ->assertSee('This verification link has expired')->assertSee('Resend verification email')
            ->assertHeader('Referrer-Policy', 'no-referrer');
    }
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    Notification::assertNothingSent();
});

test('altered links are described as invalid rather than expired', function () {
    $user = User::factory()->unverified()->create();
    $this->actingAs($user)->get(expiredMerchantLink($user).'&changed=1')->assertForbidden()
        ->assertSee('This verification link is invalid')->assertDontSee('This verification link has expired');
});

test('resend from expired link uses only the authenticated account and retains feedback', function () {
    Notification::fake();
    $owner = User::factory()->unverified()->create();
    $other = User::factory()->unverified()->create();
    $link = expiredMerchantLink($other);
    $this->actingAs($owner)->get($link)->assertForbidden()->assertSee($owner->email)->assertDontSee($other->email);
    $this->from($link)->post(route('verification.send'))->assertRedirect($link)->assertSessionHas('status', 'verification-link-sent');
    Notification::assertSentTo($owner, App\Notifications\MerchantVerifyEmail::class);
    Notification::assertNotSentTo($other, App\Notifications\MerchantVerifyEmail::class);
    $this->get($link)->assertSee('A new verification email has been queued.');
});

test('verified accounts get an account link and expired guests can sign in for a new link', function () {
    $user = User::factory()->create();
    $link = expiredMerchantLink($user);
    $this->get($link)->assertForbidden()->assertSee('Sign in to request a new link');
    $this->actingAs($user)->get($link)->assertForbidden()->assertSee('already verified')->assertDontSee('Resend verification email');
});
