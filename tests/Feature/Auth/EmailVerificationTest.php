<?php

namespace Tests\Feature\Auth;

use App\Mail\MerchantWelcomeEmail;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/verify-email');

        $response->assertStatus(200);
    }

    public function test_email_can_be_verified(): void
    {
        $user = User::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)], absolute: false
        );

        $this->actingAs($user)->get($verificationUrl)->assertOk()->assertSee('Verify my email');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $response = $this->post($verificationUrl);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')], absolute: false
        );

        $this->actingAs($user)->get($verificationUrl);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_email_can_be_confirmed_in_a_different_browser_without_signing_in(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();
        $verificationUrl = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ], absolute: false);

        $this->get($verificationUrl)->assertOk()->assertSee('Verify my email');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        Mail::assertNotQueued(MerchantWelcomeEmail::class);

        $this->post($verificationUrl)->assertOk()->assertSee('Email verified')->assertSee('Sign in');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Mail::assertQueued(MerchantWelcomeEmail::class, 1);

        $this->post($verificationUrl)->assertOk();
        Mail::assertQueued(MerchantWelcomeEmail::class, 1);
    }

    public function test_verification_link_cannot_confirm_a_different_email_or_account(): void
    {
        $user = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();
        $wrongHashUrl = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($other->email),
        ], absolute: false);

        $this->actingAs($other)->post($wrongHashUrl)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->assertFalse($other->fresh()->hasVerifiedEmail());
    }

    public function test_confirming_an_email_while_signed_in_elsewhere_is_rejected(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();
        $other = User::factory()->create();
        $verificationUrl = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ], absolute: false);

        $this->actingAs($other)->post($verificationUrl)->assertForbidden();
        $this->assertAuthenticatedAs($other);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        Mail::assertNotQueued(MerchantWelcomeEmail::class);
    }
}
