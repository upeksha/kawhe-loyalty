<?php

namespace Tests\Feature\Auth;

use App\Mail\MerchantWelcomeEmail;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertSee('Create my free café account');
        $response->assertSee('No credit card required. Takes about two minutes.');
        $response->assertSee('Use at least 8 characters.');
        $response->assertSee('Show password');
        $response->assertDontSee('name="address"', false);
    }

    public function test_new_users_can_register(): void
    {
        Mail::fake();

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'store_name' => 'Test Cafe',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice', absolute: false));

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);

        $store = Store::where('user_id', $user->id)->first();
        $this->assertNotNull($store);
        $this->assertSame('Test Cafe', $store->name);
        $this->assertNull($store->address);
        $this->assertSame(\App\Http\Controllers\MerchantOnboardingWizardController::STEP_STORE_BASICS, $store->onboarding_step);
        $this->assertNotNull($store->default_loyalty_program_id);

        Mail::assertNotQueued(MerchantWelcomeEmail::class);
        $this->assertNull($user->merchant_welcome_queued_at);
    }

    public function test_address_is_collected_during_store_setup_instead_of_registration(): void
    {
        Mail::fake();

        $this->post('/register', [
            'name' => 'Test Owner',
            'email' => 'owner@example.com',
            'store_name' => 'Test Café',
            'address' => 'Ignored at registration',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('verification.notice'));

        $store = User::where('email', 'owner@example.com')->sole()->stores()->sole();
        $this->assertNull($store->address);

        $store->user->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($store->user)
            ->post(route('merchant.onboarding.wizard.store-basics.store'), [
                'name' => 'Test Café',
                'address' => '28 Roland Hill, Auckland',
                'reward_target' => 7,
                'reward_title' => 'Free coffee',
            ])->assertRedirect(route('merchant.onboarding.wizard.card-design'));

        $this->assertSame('28 Roland Hill, Auckland', $store->fresh()->address);
    }

    public function test_failed_registration_preserves_non_secret_fields_for_retry(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'Test Owner',
            'email' => 'owner@example.com',
            'store_name' => 'Test Café',
            'password' => 'password123',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertRedirect('/register')
            ->assertSessionHasErrors('password')
            ->assertSessionHasInput('name', 'Test Owner')
            ->assertSessionHasInput('email', 'owner@example.com')
            ->assertSessionHasInput('store_name', 'Test Café');

        $this->get('/register')->assertOk()
            ->assertSee('value="Test Owner"', false)
            ->assertSee('value="owner@example.com"', false)
            ->assertSee('value="Test Café"', false);
    }

    public function test_welcome_is_queued_only_after_email_confirmation_and_only_once(): void
    {
        Mail::fake();

        $this->post('/register', [
            'name' => 'New owner',
            'email' => 'owner@example.com',
            'store_name' => 'New Cafe',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'owner@example.com')->sole();
        $verificationUrl = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ], absolute: false);

        Mail::assertNotQueued(MerchantWelcomeEmail::class);
        $this->get($verificationUrl)->assertOk();
        Mail::assertNotQueued(MerchantWelcomeEmail::class);

        $this->post($verificationUrl)->assertRedirect();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertNotNull($user->fresh()->merchant_welcome_queued_at);
        Mail::assertQueued(MerchantWelcomeEmail::class, fn (MerchantWelcomeEmail $mail) => $mail->user->is($user) && $mail->content()->with['store']?->name === 'New Cafe'
        );

        $this->post($verificationUrl)->assertRedirect();
        Mail::assertQueued(MerchantWelcomeEmail::class, 1);

        $this->patch('/profile', ['name' => $user->name, 'email' => 'new-owner@example.com'])->assertRedirect('/profile');
        $newVerificationUrl = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1('new-owner@example.com'),
        ], absolute: false);
        $this->post($newVerificationUrl)->assertRedirect();
        Mail::assertQueued(MerchantWelcomeEmail::class, 1);
    }

    public function test_welcome_queue_failure_does_not_undo_verification(): void
    {
        $user = User::factory()->unverified()->create();
        $verificationUrl = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ], absolute: false);

        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Mail queue unavailable'));

        $this->actingAs($user)->post($verificationUrl)->assertRedirect();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertNull($user->fresh()->merchant_welcome_queued_at);
    }
}
