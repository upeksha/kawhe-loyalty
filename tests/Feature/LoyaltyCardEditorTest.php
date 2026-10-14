<?php

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;

function editorStore(): Store
{
    return Store::factory()->create([
        'user_id' => User::factory()->create()->id,
        'onboarding_completed_at' => now(),
        'onboarding_step' => null,
    ]);
}

test('failed card save retains signup choices and unchecked verification', function () {
    $store = editorStore();
    $program = $store->resolvedDefaultProgram();
    $program->update(['require_verification_for_redemption' => true]);
    $url = route('merchant.stores.programs.edit', [$store, $program]);

    $this->actingAs($store->user)->from($url)
        ->put(route('merchant.stores.programs.update', [$store, $program]), [
            'name' => '',
            'reward_title' => 'Free tea',
            'reward_target' => 5,
            'require_verification_for_redemption' => '0',
            'first_name_enabled' => '0',
            'phone_enabled' => '1',
            'phone_required' => '1',
        ])->assertSessionHasErrors('name');

    $response = $this->get($url)->assertOk();
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//*[@id="require_verification_for_redemption"]/@checked')->length)->toBe(0);
    $state = $xpath->query('//*[@x-data and contains(@x-data, "registrationFormConfigState")]')->item(0)->getAttribute('x-data');
    preg_match("/JSON.parse\('(.*)'\)/", $state, $matches);
    $config = json_decode(json_decode('"'.$matches[1].'"'), true);
    expect($config['phone'])->toBe(['enabled' => true, 'required' => true]);
    expect($config['first_name'])->toBe(['enabled' => false, 'required' => false]);
    expect($xpath->query('//*[@id="signup-fields"]/@open')->length)->toBe(1);
    expect((bool) $program->fresh()->require_verification_for_redemption)->toBeTrue();
});

test('locked stamp target displays saved value after rejected change', function () {
    $store = editorStore();
    $program = $store->resolvedDefaultProgram();
    $program->update(['reward_target' => 6]);
    LoyaltyAccount::create([
        'store_id' => $store->id,
        'loyalty_program_id' => $program->id,
        'customer_id' => Customer::factory()->create()->id,
    ]);
    $url = route('merchant.stores.programs.edit', [$store, $program]);
    $this->actingAs($store->user)->from($url)
        ->put(route('merchant.stores.programs.update', [$store, $program]), [
            'name' => 'Coffee card', 'reward_title' => 'Free coffee', 'reward_target' => 2,
        ])->assertSessionHasErrors('reward_target');
    $response = $this->get($url)->assertOk();
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//*[@id="reward_target"]')->item(0)->getAttribute('value'))->toBe('6');
    expect($xpath->query('//input[@name="reward_target"]')->item(0)->getAttribute('value'))->toBe('6');
    expect($program->fresh()->reward_target)->toBe(6);
});
