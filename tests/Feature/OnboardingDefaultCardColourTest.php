<?php

use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

test('new onboarding stores start with the forest colour', function () {
    Mail::fake();
    Notification::fake();
    $this->post('/register', [
        'name' => 'Forest Demo Owner', 'email' => 'forest-demo@example.invalid',
        'store_name' => 'Forest Demo Cafe', 'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertRedirect(route('verification.notice'));
    $store = User::where('email', 'forest-demo@example.invalid')->sole()->stores()->sole();
    expect(strtoupper($store->background_color))->toBe('#263E30');
    expect(strtoupper($store->resolvedDefaultProgram()->background_color))->toBe('#263E30');
});

test('onboarding defaults unfinished placeholder colours to forest and preserves saved choices', function (string $colour, bool $saved, string $expected) {
    Storage::fake('public');
    $user = User::factory()->create();
    $store = Store::factory()->create([
        'user_id' => $user->id, 'card_design' => 'simple',
        'background_color' => $colour, 'onboarding_step' => Store::ONBOARDING_STEP_CARD_DESIGN,
        'logo_path' => $saved ? 'logos/saved.png' : null,
    ]);
    $this->actingAs($user)->get(route('merchant.onboarding.wizard.card-design'))
        ->assertOk()->assertSee('bgColor: \''.$expected.'\'', false);
    expect($store->fresh()->background_color)->toBe($colour);
})->with([
    'unfinished default' => ['#1F2937', false, '#263E30'],
    'saved original colour' => ['#1F2937', true, '#1F2937'],
    'chosen berry' => ['#8A2453', true, '#8A2453'],
]);
