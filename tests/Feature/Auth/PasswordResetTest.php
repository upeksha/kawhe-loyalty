<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\MerchantResetPassword as ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
        $this->get('/login')->assertSee(route('password.request'));
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            $this->assertTrue(Hash::check('password', $user->fresh()->password));
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'another-password',
                'password_confirmation' => 'another-password',
            ])->assertSessionHasErrors('email');
            $this->assertTrue(Hash::check('password', $user->fresh()->password));

            return true;
        });
    }

    public function test_invalid_and_expired_tokens_do_not_change_the_password(): void
    {
        $user = User::factory()->create();
        $original = $user->password;
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'email' => $user->email, 'token' => 'invalid-token',
            'password' => 'new-password', 'password_confirmation' => 'new-password',
        ])->assertSessionHasErrors('email');

        $this->travel(config('auth.passwords.users.expire') + 1)->minutes();
        $this->post('/reset-password', [
            'email' => $user->email, 'token' => $token,
            'password' => 'new-password', 'password_confirmation' => 'new-password',
        ])->assertSessionHasErrors('email');
        $this->assertSame($original, $user->fresh()->password);
    }

    public function test_password_confirmation_is_required(): void
    {
        $user = User::factory()->create();
        $original = $user->password;
        $this->post('/reset-password', [
            'email' => $user->email, 'token' => Password::createToken($user),
            'password' => 'new-password', 'password_confirmation' => 'different-password',
        ])->assertSessionHasErrors('password');
        $this->assertSame($original, $user->fresh()->password);
    }

    public function test_reset_requests_are_rate_limited(): void
    {
        Notification::fake();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password', ['email' => "missing{$i}@example.test"])->assertRedirect();
        }
        $this->post('/forgot-password', ['email' => 'missing@example.test'])->assertStatus(429);
        Notification::assertNothingSent();
    }
}
