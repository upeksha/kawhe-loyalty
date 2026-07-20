<?php

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('loyalty card edit shows separate Apple and Google previews and wallet health', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create([
        'user_id' => $user->id,
        'onboarding_step' => null,
        'onboarding_completed_at' => now(),
    ]);
    $program = $store->resolvedDefaultProgram();

    $response = $this->actingAs($user)->get(route('merchant.stores.programs.edit', [$store, $program]));

    $response->assertOk()
        ->assertSee('Apple')
        ->assertSee('Google')
        ->assertSee('Wallet health')
        ->assertSee('Apple and Google control the final Wallet layout')
        ->assertDontSee('card type', false)
        ->assertDontSee('background pattern', false)
        ->assertDontSee('stamp icon', false);
});

test('preview example progress never exceeds a low reward target', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create([
        'user_id' => $user->id,
        'onboarding_step' => null,
        'onboarding_completed_at' => now(),
        'reward_target' => 2,
    ]);
    $program = $store->resolvedDefaultProgram();
    $program->update(['reward_target' => 2]);

    $preview = app(\App\Services\Wallet\WalletPreviewDataFactory::class)->forProgram($program->fresh('store'));

    expect($preview['example_stamps'])->toBeLessThanOrEqual($preview['reward_target'])
        ->and($preview['example_stamps'])->toBe(1);
});
