<?php

use App\Models\Store;

test('join and recovery pages lead with merchant branding instead of Kawhe', function () {
    $store = Store::factory()->create(['name' => 'Neighbourhood Coffee', 'logo_path' => 'logos/store.png']);
    $program = $store->resolvedDefaultProgram();
    $program->update(['logo_path' => 'logos/card.png']);
    foreach (['join.index', 'join.show', 'join.existing'] as $route) {
        $this->get(route($route, ['slug' => $program->slug, 't' => $program->join_token]))
            ->assertOk()->assertSee('Neighbourhood Coffee logo')
            ->assertSee($program->logo_url, false)
            ->assertDontSee('aria-label="Kawhe"', false);
    }
});

test('onboarding branding falls back to store logo and keeps the name without artwork', function () {
    $store = Store::factory()->create(['name' => 'Neighbourhood Coffee', 'logo_path' => 'logos/store.png']);
    $program = $store->resolvedDefaultProgram();
    $program->update(['logo_path' => null, 'pass_logo_path' => null]);
    $url = route('join.index', ['slug' => $program->slug, 't' => $program->join_token]);
    $this->get($url)->assertOk()->assertSee($store->logo_url, false);
    $store->update(['logo_path' => null, 'pass_logo_path' => null]);
    $this->get($url)->assertOk()->assertSee('Neighbourhood Coffee')->assertDontSee('Neighbourhood Coffee logo');
});
