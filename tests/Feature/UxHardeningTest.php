<?php

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function uxStore(User $owner, array $attributes = []): Store
{
    return Store::factory()->create(array_merge([
        'user_id' => $owner->id,
        'onboarding_step' => null,
        'onboarding_completed_at' => now(),
    ], $attributes));
}

test('store edit keeps card configuration on the loyalty card screen', function () {
    $merchant = User::factory()->create();
    $store = uxStore($merchant);
    $program = $store->resolvedDefaultProgram();

    $response = $this->actingAs($merchant)->get(route('merchant.stores.edit', $store));

    $response->assertOk()
        ->assertSee('Store information')
        ->assertSee('Loyalty cards')
        ->assertSee('View all cards')
        ->assertSee($program->reward_title)
        ->assertSee($program->reward_target.' stamps')
        ->assertDontSee('Edit loyalty card')
        ->assertDontSee('View card QR')
        ->assertDontSee('Legacy fallback settings')
        ->assertDontSee('name="brand_color"', false)
        ->assertDontSee('name="pass_logo"', false)
        ->assertSee(route('merchant.stores.programs.index', $store), false);

    $this->get(route('merchant.stores.programs.index', $store))
        ->assertOk()
        ->assertSee(route('merchant.stores.programs.edit', [$store, $program]), false);
});

test('card edit identifies its store default status and configuration sections', function () {
    $merchant = User::factory()->create();
    $store = uxStore($merchant);
    $program = $store->resolvedDefaultProgram();

    $response = $this->actingAs($merchant)->get(route('merchant.stores.programs.edit', [$store, $program]));

    $response->assertOk()
        ->assertSee('Default card')
        ->assertSee('Store: '.$store->name)
        ->assertSee('Card details')
        ->assertSee('Customer sign-up form')
        ->assertSee('Apple and Google Wallet')
        ->assertSee('Apple Wallet')
        ->assertSee('Google Wallet');
});

test('join flow uses a primary join action and preserves invalid form input', function () {
    $merchant = User::factory()->create();
    $store = uxStore($merchant);
    $program = $store->resolvedDefaultProgram();

    $this->get(route('join.index', ['slug' => $program->slug, 't' => $program->join_token]))
        ->assertOk()
        ->assertSee('Get my card')
        ->assertSee('Recover an existing card')
        ->assertSee('No customer app required');

    $response = $this->from(route('join.show', ['slug' => $program->slug, 't' => $program->join_token]))
        ->post(route('join.store', ['slug' => $program->slug, 't' => $program->join_token]), [
            'email' => 'not-an-email',
            'first_name' => 'Alex',
        ]);

    $response->assertSessionHasErrors('email')
        ->assertSessionHasInput('first_name', 'Alex');
});

test('customer card leads with progress qr guidance manual code and verification status', function () {
    $merchant = User::factory()->create();
    $store = uxStore($merchant, ['reward_target' => 8, 'reward_title' => 'Free coffee']);
    $program = $store->resolvedDefaultProgram();
    $program->update(['reward_target' => 8, 'reward_title' => 'Free coffee']);
    $customer = Customer::factory()->create(['email' => 'alex@example.com']);
    $account = LoyaltyAccount::create([
        'store_id' => $store->id,
        'loyalty_program_id' => $program->id,
        'customer_id' => $customer->id,
        'stamp_count' => 5,
        'reward_balance' => 0,
    ]);

    $this->get(route('card.show', $account->public_token))
        ->assertOk()
        ->assertSee('5 of 8 stamps')
        ->assertSee('3 more until free coffee')
        ->assertSee('Show this code when you buy a coffee')
        ->assertSee('Manual code ·')
        ->assertSee('Your card is active')
        ->assertSee('Recent activity');
});

test('scanner exposes store selection manual entry progress and recovery language', function () {
    $merchant = User::factory()->create(['email_verified_at' => now()]);
    $store = uxStore($merchant);

    $response = $this->actingAs($merchant)->get(route('merchant.scanner'))
        ->assertOk()
        ->assertSee('Scan a customer card')
        ->assertSee('Enter card code')
        ->assertSee('Check card')
        ->assertSee($store->name)
        ->assertSee('Place the customer QR code inside the frame')
        ->assertSee('Current progress')
        ->assertSee('Rewards available')
        ->assertSee('Scan next customer')
        ->assertSee('This card was just scanned');

    $dom = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML($response->getContent());
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $xpath = new DOMXPath($dom);
    expect(trim($xpath->query('//label[@for="store_id"]')->item(0)->textContent))->toBe('Store');
    expect($xpath->query('//select[@id="store_id"]/option[@value="'.$store->id.'"]')->length)->toBe(1);
    expect($xpath->query('//label[@for="manual_token"]')->length)->toBe(1);
    expect($xpath->query('//input[@id="manual_token"]')->length)->toBe(1);
    expect($xpath->query('//*[@id="merchant-scanner"]//*[@role="status" and @aria-live="polite"]')->length)->toBeGreaterThan(0);
});
