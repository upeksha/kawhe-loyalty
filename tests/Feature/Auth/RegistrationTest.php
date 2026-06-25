<?php

namespace Tests\Feature\Auth;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'store_name' => 'Test Cafe',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('merchant.onboarding.wizard.store-basics', absolute: false));

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);

        $store = Store::where('user_id', $user->id)->first();
        $this->assertNotNull($store);
        $this->assertSame('Test Cafe', $store->name);
        $this->assertSame(\App\Http\Controllers\MerchantOnboardingWizardController::STEP_STORE_BASICS, $store->onboarding_step);
        $this->assertNotNull($store->default_loyalty_program_id);
    }
}
