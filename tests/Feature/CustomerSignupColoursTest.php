<?php

use App\Models\Store;
use App\Support\ProgramBranding;

test('signup actions follow saved card colours for every style', function ($style, $colour) {
    config(['app.env' => 'testing', 'app.url' => 'https://testing.kawhe.shop']);
    $store = Store::factory()->create();
    $program = $store->resolvedDefaultProgram();
    $program->update(['card_design' => $style, 'background_color' => $colour]);
    $theme = ProgramBranding::signupColors($colour);
    $this->get(route('join.index', ['slug' => $program->slug, 't' => $program->join_token]))
        ->assertOk()->assertSee('customer-signup-theme')
        ->assertSee('--signup-button: '.$colour, false)
        ->assertSee('--signup-text: '.$theme['text'], false)
        ->assertSee('--signup-link: '.$theme['link'], false);
})->with(['simple', 'illustrated', 'photo'])->with(['#263E30', '#FAEDD7', '#FFFFFF', '#777777']);

test('light card colours receive dark button text and darker links', function () {
    $theme = ProgramBranding::signupColors('#FAEDD7');
    expect($theme['button'])->toBe('#FAEDD7');
    expect($theme['text'])->toBe('#000000');
    expect($theme['link'])->not->toBe('#FAEDD7');
    expect(ProgramBranding::signupColors('#263E30')['text'])->toBe('#FFFFFF');
});


test('customer card actions use the saved colour across all styles', function ($style, $colour) {
    config(['app.env' => 'testing', 'app.url' => 'https://testing.kawhe.shop']);
    $store = Store::factory()->create();
    $program = $store->resolvedDefaultProgram();
    $program->update(['card_design' => $style, 'background_color' => $colour]);
    $account = \App\Models\LoyaltyAccount::factory()->create(['store_id' => $store->id, 'loyalty_program_id' => $program->id]);
    $theme = ProgramBranding::signupColors($colour);
    $this->get(route('card.show', $account->public_token))->assertOk()
        ->assertSee('--signup-button: '.$colour, false)->assertSee('--signup-text: '.$theme['text'], false)
        ->assertSee('--signup-link: '.$theme['link'], false)->assertSee('Save to Wallet')->assertSee('Refresh card');
})->with(['simple', 'illustrated', 'photo'])->with(['#263E30', '#FAEDD7', '#FFFFFF', '#777777']);

test('photo web cards use live stamp circles without duplicate artwork', function ($target) {
    config(['app.env' => 'testing', 'app.url' => 'https://testing.kawhe.shop']);
    $store = Store::factory()->create();
    $program = $store->resolvedDefaultProgram();
    $program->update(['card_design' => 'photo', 'reward_target' => $target]);
    $account = \App\Models\LoyaltyAccount::factory()->create(['store_id' => $store->id, 'loyalty_program_id' => $program->id, 'stamp_count' => 2]);
    $response = $this->get(route('card.show', $account->public_token))->assertOk()
        ->assertDontSee('<img id="coffee-sample-art"', false)->assertSee('data-photo="true"', false);
    if ($target <= 20) {
        $response->assertSee('Stamp 1: collected')->assertSee('Stamp 3: empty');
    } else {
        $response->assertSee('id="photo-stamp-progress"', false);
    }
})->with([6, 8, 20, 30]);
