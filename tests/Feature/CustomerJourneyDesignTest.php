<?php

use App\Models\LoyaltyAccount;
use App\Models\Store;
use Illuminate\Support\Facades\URL;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

it('keeps merchant configured signup fields and program scoped form actions', function () {
    $store = Store::factory()->create();
    $program = $store->resolvedDefaultProgram();
    $program->update(['registration_form_config' => [
        'first_name' => ['enabled' => true, 'required' => true],
        'last_name' => ['enabled' => false, 'required' => false],
        'phone' => ['enabled' => true, 'required' => false],
        'birthday' => ['enabled' => true, 'required' => false],
    ]]);
    $params = ['slug' => $program->slug, 't' => $program->join_token];
    $response = $this->get(route('join.show', $params))->assertOk()
        ->assertSee('Create my card')->assertSee('name="email"', false)
        ->assertSee('name="first_name"', false)->assertSee('name="phone"', false)
        ->assertSee('name="birthday"', false)->assertDontSee('name="last_name"', false)
        ->assertSee(e(route('join.store', $params)), false);
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    expect($dom->getElementById('first_name')->hasAttribute('required'))->toBeTrue();
    expect($dom->getElementById('phone')->hasAttribute('required'))->toBeFalse();
    $this->get(route('join.existing', $params))->assertOk()
        ->assertSee('name="phone"', false)->assertSee('Send recovery email');
});

it('keeps real stamp and reward codes, signed wallet URLs and partial reward state in the redesigned card', function () {
    $store = Store::factory()->create(['background_color' => '#254b3a', 'brand_color' => '#f2b04d']);
    $program = $store->resolvedDefaultProgram();
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id, 'loyalty_program_id' => $program->id,
        'stamp_count' => 2, 'reward_balance' => 2, 'reward_redeemed_at' => now(),
        'redeem_token' => 'design-reward-token', 'verified_at' => now(),
    ]);
    $this->get(route('card.show', $account->public_token))->assertOk()
        ->assertSee((string) QrCode::size(220)->errorCorrection('L')->margin(2)->generate('LA:'.$account->public_token), false)
        ->assertSee((string) QrCode::size(250)->errorCorrection('L')->margin(1)->generate('LR:'.$account->redeem_token), false)
        ->assertSee(e(URL::signedRoute('wallet.apple.download', ['public_token' => $account->public_token])), false)
        ->assertSee(e(URL::signedRoute('wallet.google.save', ['public_token' => $account->public_token])), false)
        ->assertSee('rewardBalance: 2', false)->assertSee('rewardRedeemed: false', false)
        ->assertSee('Remaining after redeem: 1')->assertSee('Save to Wallet')->assertSee('Refresh card')
        ->assertSee('Recent activity')->assertSee('Add to Home Screen')
        ->assertSee('--program-bg: #254B3A', false)
        ->assertDontSee('See reward-ready state')->assertDontSee('Demo card')
        ->assertDontSee($account->wallet_auth_token);
    expect($account->fresh()->reward_balance)->toBe(2);
});

it('keeps unverified card guidance and gives unavailable cards the shared customer help links', function () {
    $account = LoyaltyAccount::factory()->create(['verified_at' => null]);
    $this->get(route('card.show', $account->public_token))->assertOk()
        ->assertSee('Your card is active')->assertSee('Send verification email')
        ->assertSee('Verify your email before redeeming a reward.');
    $this->get(route('card.show', 'missing-customer-card'))->assertNotFound()
        ->assertSee('Card not found')->assertSee('https://kawhe.shop/privacy-policy')
        ->assertSee('mailto:support@kawhe.shop')->assertSee('customer-redesign');
});
