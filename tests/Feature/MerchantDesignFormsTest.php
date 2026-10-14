<?php

use App\Models\LoyaltyProgram;
use App\Models\Store;
use App\Models\User;
use Laravel\Cashier\Subscription;

function merchantDesignDom(string $html): DOMXPath
{
    $dom = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return new DOMXPath($dom);
}

test('saving a card and refreshing wallets are independent forms', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->for($owner)->create();
    $program = $store->resolvedDefaultProgram();
    $response = $this->actingAs($owner)->get(route('merchant.stores.programs.edit', [$store, $program]))->assertOk();
    $dom = merchantDesignDom($response->getContent());
    $update = $dom->query('//form[@id="loyalty-card-form"]')->item(0);
    expect($update->getAttribute('action'))->toBe(route('merchant.stores.programs.update', [$store, $program]));
    expect($dom->query('.//input[@name="_method" and @value="PUT"]', $update)->length)->toBe(1);
    expect($dom->query('.//input[@name="reward_title"]', $update)->length)->toBe(1);
    expect($dom->query('.//button[@type="submit"]', $update)->length)->toBe(1);
    $refreshUrl = route('merchant.stores.programs.refresh-wallets', [$store, $program]);
    $refresh = $dom->query('//form[@action="'.$refreshUrl.'"]')->item(0);
    expect($refresh)->not->toBeNull();
    expect($dom->query('ancestor::form', $refresh)->length)->toBe(0);
    expect($dom->query('.//input[@name="_method"]', $refresh)->length)->toBe(0);
    expect($dom->query('.//input[@name="_token"]', $refresh)->length)->toBe(1);
});

test('create store submit stays inside the same validation scope as the upload fields', function () {
    $owner = User::factory()->create(['stripe_id' => 'cus_design']);
    Store::factory()->for($owner)->create();
    Subscription::create(['user_id' => $owner->id, 'name' => 'default', 'stripe_id' => 'sub_design', 'stripe_status' => 'active', 'quantity' => 1]);
    $response = $this->actingAs($owner)->get(route('merchant.stores.create'))->assertOk();
    $dom = merchantDesignDom($response->getContent());
    $form = $dom->query('//form[@id="store-create-form"]')->item(0);
    expect($form->hasAttribute('x-data'))->toBeTrue();
    expect($dom->query('.//button[@type="submit"]', $form)->length)->toBe(1);
    expect($dom->query('.//input[@type="file"]', $form)->length)->toBe(3);
});

test('card workspace preserves archive restore and default card protections', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->for($owner)->create();
    $default = $store->resolvedDefaultProgram();
    $active = LoyaltyProgram::create(['store_id' => $store->id, 'name' => 'Tea card', 'reward_title' => 'Free tea', 'reward_target' => 1]);
    $archived = LoyaltyProgram::create(['store_id' => $store->id, 'name' => 'Archived coffee', 'reward_title' => 'Coffee', 'reward_target' => 5]);
    $archived->delete();
    $otherStore = Store::factory()->create(['name' => 'Private other merchant']);
    $otherStore->resolvedDefaultProgram();
    $response = $this->actingAs($owner)->get(route('merchant.programs.index'))->assertOk()
        ->assertSee('Tea card')->assertSee('Archived coffee')->assertSee('0 of 1 stamps')->assertDontSee('Private other merchant');
    $dom = merchantDesignDom($response->getContent());
    expect($dom->query('//form[@action="'.route('merchant.stores.programs.destroy', [$store, $active]).'"]')->length)->toBe(1);
    expect($dom->query('//form[@action="'.route('merchant.stores.programs.destroy', [$store, $default]).'"]')->length)->toBe(0);
    expect($dom->query('//form[@action="'.route('merchant.stores.programs.restore', [$store, $archived->id]).'"]')->length)->toBe(1);
});

test('owners with only archived stores can still add an allowed store', function () {
    $owner = User::factory()->create(['stripe_id' => 'cus_archived_design']);
    Subscription::create(['user_id' => $owner->id, 'name' => 'default', 'stripe_id' => 'sub_archived_design', 'stripe_status' => 'active', 'quantity' => 1]);
    $store = Store::factory()->for($owner)->create();
    $store->delete();
    $this->actingAs($owner)->get(route('merchant.stores.index'))->assertOk()
        ->assertSee('Add Another Store')->assertSee(route('merchant.stores.create'));
});
