<?php

use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;
use App\Services\Wallet\Artwork\WalletDesignHasher;
use App\Support\CardDesign;
use Laravel\Cashier\Subscription;

test('a cafe can create, edit and reset the text colour of a loyalty card', function () {
    config(['app.env' => 'testing', 'app.url' => 'https://testing.kawhe.shop']);
    $owner = User::factory()->create(['stripe_id' => 'cus_card_colour']);
    $store = Store::factory()->create(['user_id' => $owner->id]);
    Subscription::create([
        'user_id' => $owner->id,
        'name' => 'default',
        'stripe_id' => 'sub_card_colour',
        'stripe_status' => 'active',
        'quantity' => 1,
    ]);

    $payload = ['name' => 'Studio card', 'card_design' => 'simple', 'background_color' => '#25315A',
        'reward_target' => 7, 'reward_title' => 'Free coffee', 'card_text_color' => '#FFF4CF'];

    $this->actingAs($owner)->post(route('merchant.stores.programs.store', $store), $payload)->assertRedirect();
    $card = $store->loyaltyPrograms()->where('name', $payload['name'])->sole();
    expect($card->card_text_color)->toBe('#FFF4CF');
    $edit = route('merchant.stores.programs.edit', [$store, $card]);
    $update = route('merchant.stores.programs.update', [$store, $card]);
    $this->get($edit)->assertOk()->assertSee('Card text colour')->assertSee('Use automatic');
    $this->from($edit)->put($update, [...$payload, 'card_text_color' => '#27345A'])
        ->assertSessionHasErrors('card_text_color');
    expect($card->fresh()->card_text_color)->toBe('#FFF4CF');

    $account = LoyaltyAccount::factory()->create(['store_id' => $store->id, 'loyalty_program_id' => $card->id]);
    $this->get(route('card.show', ['public_token' => $account->public_token]))
        ->assertOk()->assertSee('color: #FFF4CF !important', false);
    $hash = app(WalletDesignHasher::class)->hash($card, 'logo', 'hero');

    $this->put($update, [...$payload, 'card_text_color' => ''])->assertRedirect($edit);
    expect($card->fresh()->card_text_color)->toBeNull();
    expect(app(WalletDesignHasher::class)->hash($card->fresh(), 'logo', 'hero'))->not->toBe($hash);
});

test('photo card overrides are also checked against the image overlay', function () {
    $store = Store::factory()->make(['card_design' => 'photo', 'background_color' => '#FFFFFF']);
    $store->card_text_color = '#000000';
    expect(CardDesign::customTextColor($store))->toBeNull();

    $store->background_color = '#25315A';
    $store->card_text_color = '#FFF4CF';
    expect(CardDesign::customTextColor($store))->toBe('#FFF4CF');
});

test('both poster previews use the card text colour when it contrasts with the actual poster background', function () {
    config(['app.env' => 'testing', 'app.url' => 'https://testing.kawhe.shop']);
    $store = Store::factory()->create();
    $program = $store->resolvedDefaultProgram();
    $program->forceFill(['card_design' => 'simple', 'background_color' => '#25315A', 'card_text_color' => '#FFF4CF'])->save();
    $routes = [
        'merchant.stores.qr.pdf' => [$store, 'preview' => 1],
        'merchant.stores.programs.qr.pdf' => [$store, $program, 'preview' => 1],
    ];

    $this->actingAs($store->user);
    foreach ($routes as $route => $parameters) {
        $this->get(route($route, $parameters))->assertOk()
            ->assertSee('background: #25315A; color: #FFF4CF;', false)
            ->assertSee('alt="QR Code"', false)
            ->assertSee('background: #ffffff;', false);
    }

    $program->forceFill([
        'card_design' => 'classic', 'background_color' => '#25315A', 'brand_color' => '#BFA87A', 'card_text_color' => '#FFF4CF',
    ])->save();
    expect(CardDesign::customTextColor($program))->toBe('#FFF4CF');
    $this->get(route('merchant.stores.programs.qr.pdf', [$store, $program, 'preview' => 1]))
        ->assertOk()->assertSee('background: #BFA87A; color: #111827;', false)
        ->assertDontSee('#FFF4CF');
});
