<?php

use App\Models\User;

test('unauthenticated user redirected to login for dashboard', function () {
    $response = $this->get('/dashboard');

    $response->assertRedirect('/login');
});

test('authenticated user without a store is redirected to setup wizard from dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertRedirect(route('merchant.onboarding.wizard.store-basics'));
});
