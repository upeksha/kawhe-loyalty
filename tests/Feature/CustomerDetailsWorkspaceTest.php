<?php

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\StampEvent;
use App\Models\Store;
use App\Models\User;

function customerDetailsDom(string $html): DOMXPath
{
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return new DOMXPath($document);
}

test('customer details use email fallback and retain protected support actions in collapsed help', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->for($owner)->create();
    $customer = Customer::factory()->create(['name' => null, 'first_name' => null, 'last_name' => null, 'email' => 'customer@example.test', 'phone' => null]);
    $account = LoyaltyAccount::factory()->create(['store_id' => $store->id, 'customer_id' => $customer->id]);
    $response = $this->actingAs($owner)->get(route('merchant.customers.show', [$account, 'q' => 'customer', 'store_id' => $store->id]))->assertOk()->assertDontSee('Public Token:')->assertDontSee('Phone:');
    $dom = customerDetailsDom($response->getContent());
    expect($dom->query('//h1')->item(0)->textContent)->toBe('customer@example.test');
    expect($dom->query('//details[@open]')->length)->toBe(0);
    expect($dom->query('//details//aside[@aria-label="Customer card preview"]')->length)->toBe(1);
    foreach (['resend-welcome', 'sync-wallet'] as $action) {
        expect($dom->query('//details[@id="customer-help"]//form[@method="POST" and @action="'.route('merchant.customers.'.$action, $account).'"]/input[@name="_token"]')->length)->toBe(1);
    }
    expect($dom->query('//a[@href="'.route('card.show', $account->public_token).'"]')->length)->toBe(1);
    expect($dom->query('//a[@href="'.route('merchant.customers.edit', $account).'"]')->length)->toBe(1);
    $back = $dom->query('//a[contains(text(),"Back to customers")]')->item(0);
    parse_str(parse_url($back->getAttribute('href'), PHP_URL_QUERY), $query);
    expect($query)->toBe(['q' => 'customer', 'store_id' => (string) $store->id]);
});

test('verification message follows the loyalty program rule and only offers resend when needed', function (bool $required, bool $verified) {
    $owner = User::factory()->create();
    $store = Store::factory()->for($owner)->create(['require_verification_for_redemption' => ! $required]);
    $program = $store->resolvedDefaultProgram();
    $program->update(['require_verification_for_redemption' => $required]);
    $account = LoyaltyAccount::factory()->create(['store_id' => $store->id, 'loyalty_program_id' => $program->id, 'verified_at' => $verified ? now() : null]);
    $response = $this->actingAs($owner)->get(route('merchant.customers.show', $account))->assertOk();
    $dom = customerDetailsDom($response->getContent());
    expect($dom->query('//form[@action="'.route('merchant.customers.resend-verification', $account).'"]')->length)->toBe($verified ? 0 : 1);
    if ($verified) {
        $response->assertDontSee('before redeeming rewards.')->assertDontSee('verification is optional');
    } elseif ($required) {
        $response->assertSee('This customer needs to verify their email before redeeming rewards.');
    } else {
        $response->assertSee('Email verification is optional for redeeming rewards on this card.');
    }
})->with([[true, false], [false, false], [true, true], [false, true]]);

test('older stamp history stays available behind the activity disclosure', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->for($owner)->create();
    $account = LoyaltyAccount::factory()->create(['store_id' => $store->id]);
    foreach (range(1, 7) as $index) {
        StampEvent::forceCreate(['store_id' => $store->id, 'loyalty_account_id' => $account->id, 'user_id' => $owner->id, 'type' => 'stamp', 'count' => $index, 'created_at' => now()->subMinutes($index)]);
    }
    $response = $this->actingAs($owner)->get(route('merchant.customers.show', $account))->assertOk();
    $dom = customerDetailsDom($response->getContent());
    $more = $dom->query('//details[summary[contains(text(),"Show 2 more activities")]]')->item(0);
    expect($more)->not->toBeNull();
    expect($dom->query('.//li', $more)->length)->toBe(2);
    expect($dom->query('//li[.//p[contains(text(),"Stamps added")]]')->length)->toBe(7);
});
