<?php

use App\Models\LoyaltyAccount;
use App\Models\User;
use Illuminate\Support\Facades\URL;

test('customer link GET and HEAD never verify or consume token and POST does', function () {
    $a = LoyaltyAccount::factory()->create(['verified_at' => null, 'email_verification_token_hash' => hash('sha256', 'confirmation-token'), 'email_verification_expires_at' => now()->addHour()]);
    $url = route('customer.email.verification.verify', ['token' => 'confirmation-token']);
    $this->get($url)->assertOk()->assertSee('Verify my email')->assertSee('method="POST"', false);
    $this->head($url)->assertOk();
    expect($a->fresh()->verified_at)->toBeNull();
    expect($a->fresh()->email_verification_token_hash)->toBe(hash('sha256', 'confirmation-token'));
    $this->post($url)->assertRedirect();
    expect($a->fresh()->verified_at)->not->toBeNull();
    expect($a->fresh()->email_verification_token_hash)->toBeNull();
    $this->post($url)->assertSessionHasErrors('email');
});

test('expired customer token cannot be confirmed after page was opened', function () {
    $a = LoyaltyAccount::factory()->create(['verified_at' => null, 'email_verification_token_hash' => hash('sha256', 'expiring'), 'email_verification_expires_at' => now()->addMinute()]);
    $url = route('customer.email.verification.verify', ['token' => 'expiring']);
    $this->get($url)->assertOk();
    $this->travel(2)->minutes();
    $this->post($url)->assertSessionHasErrors('email');
    expect($a->fresh()->verified_at)->toBeNull();
});

test('signed legacy link requires confirmation and revalidates signature', function () {
    $a = LoyaltyAccount::factory()->create(['verified_at' => null]);
    $url = URL::temporarySignedRoute('card.verification.verify', now()->addHour(), ['public_token' => $a->public_token, 'id' => $a->id, 'hash' => sha1($a->customer->email)]);
    $this->get($url)->assertOk();
    $this->head($url)->assertOk();
    expect($a->fresh()->verified_at)->toBeNull();
    $this->post($url.'&tampered=1')->assertForbidden();
    expect($a->fresh()->verified_at)->toBeNull();
    $this->post($url)->assertRedirect();
    expect($a->fresh()->verified_at)->not->toBeNull();
});

test('merchant preview does not verify and POST rejects another account', function () {
    $u = User::factory()->unverified()->create();
    $other = User::factory()->unverified()->create();
    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $u->id, 'hash' => sha1($u->email)], absolute: false);
    $this->actingAs($u)->get($url)->assertOk();
    $this->head($url)->assertOk();
    expect($u->fresh()->hasVerifiedEmail())->toBeFalse();
    $this->actingAs($other)->post($url)->assertForbidden();
    expect($u->fresh()->hasVerifiedEmail())->toBeFalse();
    $this->actingAs($u)->post($url)->assertRedirect();
    expect($u->fresh()->hasVerifiedEmail())->toBeTrue();
});
