<?php

use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;
use App\Services\Wallet\Artwork\CardStyleArtwork;
use App\Services\Wallet\Artwork\WalletDesignHasher;
use App\Support\CardDesign;
use App\Support\CoffeeCardSample;
use App\Support\StoreAssets;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['app.env' => 'testing', 'app.url' => 'https://testing.kawhe.shop', 'filesystems.assets_disk' => 'public']);
    Storage::fake('public');
    Queue::fake();
});

test('stamp shapes and reward icons save across styles without changing balances', function (string $style, string $shape, string $icon) {
    $store = Store::factory()->create();
    $program = $store->resolvedDefaultProgram();
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id, 'loyalty_program_id' => $program->id, 'stamp_count' => 2, 'reward_balance' => 1,
    ]);
    $payload = [
        'name' => 'Appearance test', 'card_design' => $style,
        'reward_target' => $program->reward_target, 'reward_title' => $program->reward_title,
        'stamp_icon' => 'heart', 'stamp_shape' => $shape, 'reward_icon' => $icon,
    ];
    if ($style === 'photo') {
        $payload['card_photo'] = UploadedFile::fake()->image('photo.jpg', 900, 600);
    }
    $url = route('merchant.stores.programs.update', [$store, $program]);
    $this->actingAs($store->user)->put($url, $payload)->assertSessionHasNoErrors()->assertRedirect();
    $program->refresh();
    expect($program->stamp_shape)->toBe($shape)
        ->and($program->reward_icon)->toBe($icon)
        ->and($store->fresh()->stamp_shape)->toBe($shape)
        ->and($store->fresh()->reward_icon)->toBe($icon)
        ->and($account->fresh()->stamp_count)->toBe(2)
        ->and($account->fresh()->reward_balance)->toBe(1);
    $this->get(route('merchant.stores.programs.edit', [$store, $program]))
        ->assertOk()->assertSee('Stamp shape')->assertSee('Reward icon');
    // Older clients can save unrelated fields without overwriting these choices.
    $this->put($url, array_diff_key($payload, array_flip(['stamp_shape', 'reward_icon', 'card_photo'])))
        ->assertSessionHasNoErrors();
    expect($program->fresh()->stamp_shape)->toBe($shape)
        ->and($program->fresh()->reward_icon)->toBe($icon);
})->with(['simple', 'illustrated', 'photo'])->with(['circle', 'square', 'rounded'])->with(['match', 'gift', 'star']);

test('legacy cards retain circle and matched reward artwork until customized', function (string $platform) {
    $store = Store::factory()->make(['card_design' => 'simple', 'background_color' => '#263E30']);
    $options = CardDesign::options($store);
    expect($options)->not->toHaveKey('shape')->not->toHaveKey('reward_icon');
    $renderer = app(CardStyleArtwork::class);
    foreach ([false, true] as $ready) {
        expect($renderer->render(7, $ready ? 0 : 4, $ready, $platform, $options))
            ->toBe($renderer->render(7, $ready ? 0 : 4, $ready, $platform, $options + ['shape' => 'circle', 'reward_icon' => 'match']));
    }
})->with(['web', 'apple', 'google']);

test('all platforms render distinct shapes and reward icons in the appropriate state', function (string $platform) {
    $options = CardDesign::options(Store::factory()->make(['card_design' => 'simple']));
    $renderer = app(CardStyleArtwork::class);
    $collecting = [];
    $ready = [];
    foreach (['circle', 'square', 'rounded'] as $shape) {
        $collecting[] = $renderer->render(7, 4, false, $platform, $options + ['shape' => $shape]);
        foreach (['match', 'gift', 'star'] as $reward) {
            $ready[] = $renderer->render(7, 0, true, $platform, $options + ['shape' => $shape, 'reward_icon' => $reward]);
        }
    }
    expect(array_unique(array_map('sha1', $collecting)))->toHaveCount(3)
        ->and(array_unique(array_map('sha1', $ready)))->toHaveCount(9);
    expect($renderer->render(7, 4, false, $platform, $options + ['reward_icon' => 'gift']))->toBe($collecting[0]);
    expect($renderer->render(100, 0, true, $platform, $options + ['reward_icon' => 'gift']))
        ->not->toBe($renderer->render(100, 0, true, $platform, $options + ['reward_icon' => 'star']));
})->with(['web', 'apple', 'google']);

test('invalid appearance values and another merchant cannot change a card', function () {
    $store = Store::factory()->create();
    $program = $store->resolvedDefaultProgram();
    $payload = ['name' => 'Test', 'stamp_shape' => 'triangle', 'reward_icon' => 'unsupported'];
    $url = route('merchant.stores.programs.update', [$store, $program]);
    $this->actingAs($store->user)->put($url, $payload)->assertSessionHasErrors(['stamp_shape', 'reward_icon']);
    expect($program->fresh()->stamp_shape)->toBeNull()->and($program->fresh()->reward_icon)->toBeNull();
    $otherStore = Store::factory()->create();
    $this->actingAs($otherStore->user)->put($url, ['stamp_shape' => 'square', 'reward_icon' => 'gift'])->assertNotFound();
});

test('onboarding saves appearance to its default program and preview stays stateless', function () {
    $store = Store::factory()->create(['onboarding_step' => Store::ONBOARDING_STEP_CARD_DESIGN]);
    $this->actingAs($store->user);
    $preview = ['store_id' => $store->id, 'card_design' => 'simple', 'background_color' => '#263E30',
        'reward_target' => 7, 'stamp_shape' => 'rounded', 'reward_icon' => 'gift', 'platform' => 'apple', 'ready' => true];
    $this->post(route('merchant.card-design.preview'), $preview)->assertOk()->assertHeader('Content-Type', 'image/png');
    expect($store->fresh()->stamp_shape)->toBeNull();
    $this->post(route('merchant.card-design.preview'), [...$preview, 'reward_icon' => 'invalid'])
        ->assertSessionHasErrors('reward_icon');
    $this->post(route('merchant.onboarding.wizard.card-design.store'), [
        'card_design' => 'simple', 'background_color' => '#263E30', 'brand_color' => '#0EA5E9',
        'stamp_shape' => 'rounded', 'reward_icon' => 'gift',
        'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        'pass_logo' => UploadedFile::fake()->image('wallet.png', 200, 80),
    ])->assertSessionHasNoErrors()->assertRedirect(route('merchant.onboarding.wizard.customer-form'));
    expect($store->fresh()->stamp_shape)->toBe('rounded')
        ->and($store->fresh()->resolvedDefaultProgram()->reward_icon)->toBe('gift');
    $this->get(route('merchant.onboarding.wizard.card-design'))->assertOk()->assertSee('Rounded')->assertSee('Gift');
});

test('appearance changes invalidate artwork caches and Wallet design hashes', function () {
    $store = Store::factory()->create();
    $program = $store->resolvedDefaultProgram();
    $program->update(['card_design' => 'simple']);
    $account = LoyaltyAccount::factory()->create(['store_id' => $store->id, 'loyalty_program_id' => $program->id, 'stamp_count' => 0, 'reward_balance' => 1]);
    $hash = app(WalletDesignHasher::class)->hash($program, 'logo', 'hero');
    $oldArt = CoffeeCardSample::artwork($account, 'apple');
    $program->update(['stamp_shape' => 'square', 'reward_icon' => 'gift']);
    $newArt = CoffeeCardSample::artwork($account->fresh(), 'apple');
    expect($newArt)->not->toBe($oldArt)
        ->and(StoreAssets::get($newArt))->not->toBe(StoreAssets::get($oldArt))
        ->and(app(WalletDesignHasher::class)->hash($program->fresh(), 'logo', 'hero'))->not->toBe($hash);
});

test('Photo browser cards show the selected shape and refresh reward-ready artwork', function () {
    $store = Store::factory()->create();
    $program = $store->resolvedDefaultProgram();
    $program->update(['card_design' => 'photo', 'stamp_shape' => 'square', 'reward_icon' => 'gift']);
    $account = LoyaltyAccount::factory()->create(['store_id' => $store->id, 'loyalty_program_id' => $program->id, 'stamp_count' => 0, 'reward_balance' => 1]);
    $this->get(route('card.show', ['public_token' => $account->public_token]))->assertOk()
        ->assertSee('data-shape="square"', false)->assertSee('id="photo-reward-art"', false);
    $this->get(route('card.api', ['public_token' => $account->public_token]))->assertOk()
        ->assertJsonPath('stamp_count', 0)->assertJsonPath('reward_balance', 1)->assertJsonPath('stamp_shape', 'square');
});


test('Google Wallet uses the selected reward artwork and updates when the design changes', function () {
    config(['app.env' => 'production', 'app.url' => 'https://app.kawhe.shop']);
    $store = Store::factory()->create();
    $program = $store->resolvedDefaultProgram();
    $program->update(['card_design' => 'simple', 'stamp_shape' => 'rounded', 'reward_icon' => 'gift']);
    $account = LoyaltyAccount::factory()->create(['store_id' => $store->id, 'loyalty_program_id' => $program->id, 'stamp_count' => 0, 'reward_balance' => 1]);
    $service = (new ReflectionClass(\App\Services\Wallet\GoogleWalletPassService::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod($service, 'buildStampStripImage');
    $giftUrl = $method->invoke($service, $account)->getSourceUri()->getUri();
    expect($giftUrl)->toContain(basename(CoffeeCardSample::artwork($account, 'google')));
    $program->update(['reward_icon' => 'star']);
    $starUrl = $method->invoke($service, $account->fresh())->getSourceUri()->getUri();
    expect($starUrl)->not->toBe($giftUrl);
});
