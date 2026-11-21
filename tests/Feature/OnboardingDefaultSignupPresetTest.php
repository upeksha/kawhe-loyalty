<?php

use App\Models\Store;
use App\Models\User;
use App\Support\RegistrationFormConfig;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

test('new cafe accounts start with balanced customer signup fields', function () {
    Mail::fake();
    Notification::fake();
    $this->post('/register', [
        'name' => 'Balanced Demo Owner', 'email' => 'balanced-demo@example.invalid',
        'store_name' => 'Balanced Demo Cafe', 'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertRedirect(route('verification.notice'));
    $store = User::where('email', 'balanced-demo@example.invalid')->sole()->stores()->sole();
    $config = $store->registration_form_config;
    expect($config)->toBe(RegistrationFormConfig::onboardingDefaults());
    expect($config['email'])->toBe(['enabled' => true, 'required' => true]);
    expect($config['first_name'])->toBe(['enabled' => true, 'required' => false]);
    expect($config['birthday'])->toBe(['enabled' => true, 'required' => false]);
    expect($config['last_name']['enabled'])->toBeFalse();
    expect($config['phone']['enabled'])->toBeFalse();
    expect($store->resolvedDefaultProgram()->registration_form_config)->toBe($config);
});

test('customer signup defaults apply only to unconfigured onboarding steps', function (string $step, bool $balanced) {
    $user = User::factory()->create();
    $config = RegistrationFormConfig::normalize([]);
    $store = Store::factory()->create([
        'user_id' => $user->id, 'onboarding_step' => $step,
        'registration_form_config' => $config,
    ]);
    $response = $this->actingAs($user)->get(route('merchant.onboarding.wizard.customer-form'))->assertOk();
    expect($response->viewData('config'))->toBe($balanced ? RegistrationFormConfig::onboardingDefaults() : $config);
    expect($store->fresh()->registration_form_config)->toBe($config);
})->with([
    'first visit defaults to balanced' => [Store::ONBOARDING_STEP_CUSTOMER_FORM, true],
    'saved email only choice stays unchanged' => [Store::ONBOARDING_STEP_CARD_READY, false],
]);
