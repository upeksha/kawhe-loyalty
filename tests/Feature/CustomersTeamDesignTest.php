<?php

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;

function customersTeamDom(string $html): DOMXPath
{
    $dom = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return new DOMXPath($dom);
}

test('failed invitation preserves unchecked permissions only for its store', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->for($owner)->create();
    $second = Store::factory()->for($owner)->create();
    $response = $this->actingAs($owner)->withSession(['_old_input' => ['invitation_store_id' => $store->id, 'email' => 'barista@example.com', 'can_stamp' => '1', 'can_redeem' => '0']])->get(route('merchant.staff.index'))->assertOk();
    $dom = customersTeamDom($response->getContent());
    $form = $dom->query('//form[@action="'.route('merchant.staff.invite', $store).'"]')->item(0);
    expect($dom->query('.//input[@type="checkbox" and @name="can_stamp" and @checked]', $form)->length)->toBe(1);
    expect($dom->query('.//input[@type="checkbox" and @name="can_redeem" and @checked]', $form)->length)->toBe(0);
    expect($dom->query('.//input[@type="hidden" and @name="can_redeem" and @value="0"]', $form)->length)->toBe(1);
    $other = $dom->query('//form[@action="'.route('merchant.staff.invite', $second).'"]')->item(0);
    expect($dom->query('.//input[@name="email"]', $other)->item(0)->getAttribute('value'))->toBe('');
});

test('customer list preserves lookup fields and uses actual available reward balance', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->for($owner)->create();
    $customer = Customer::factory()->create(['name' => 'Alex Morgan', 'phone' => '021555123', 'birthday' => '1990-01-02']);
    $account = LoyaltyAccount::factory()->create(['store_id' => $store->id, 'customer_id' => $customer->id, 'reward_balance' => 2, 'reward_redeemed_at' => now()->subDay()]);
    $response = $this->actingAs($owner)->get(route('merchant.customers.index', ['q' => 'Alex', 'store_id' => $store->id]))->assertOk()->assertSee('2 available')->assertSee('021555123')->assertSee('Jan 02, 1990')->assertSee($account->manual_entry_code);
    $dom = customersTeamDom($response->getContent());
    $links = $dom->query('//a[contains(@href,"/customers/'.$account->id.'?")]');
    expect($links->length)->toBeGreaterThan(0);
    parse_str(parse_url($links->item(0)->getAttribute('href'), PHP_URL_QUERY), $query);
    expect($query['q'])->toBe('Alex')->and($query['store_id'])->toBe((string) $store->id);
    $this->get(route('merchant.customers.show', $account))->assertOk()->assertSee('2 rewards available')->assertSee('Help with this card');
});
