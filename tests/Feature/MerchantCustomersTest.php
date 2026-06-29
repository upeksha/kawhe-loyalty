<?php

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;
use Laravel\Cashier\Subscription;

test('merchant sees customers across all their stores', function () {
    $userA = User::factory()->create();

    $storeA1 = Store::factory()->create(['user_id' => $userA->id, 'name' => 'Store A1']);
    $storeA2 = Store::factory()->create(['user_id' => $userA->id, 'name' => 'Store A2']);

    $customer1 = Customer::factory()->create(['email' => 'customer1@example.com', 'name' => 'Customer One']);
    $customer2 = Customer::factory()->create(['email' => 'customer2@example.com', 'name' => 'Customer Two']);
    $customer3 = Customer::factory()->create(['email' => 'customer3@example.com', 'name' => 'Customer Three']);

    $account1 = LoyaltyAccount::factory()->create([
        'store_id' => $storeA1->id,
        'customer_id' => $customer1->id,
    ]);

    $account2 = LoyaltyAccount::factory()->create([
        'store_id' => $storeA1->id,
        'customer_id' => $customer2->id,
    ]);

    $account3 = LoyaltyAccount::factory()->create([
        'store_id' => $storeA2->id,
        'customer_id' => $customer3->id,
    ]);

    $response = $this->actingAs($userA)->get(route('merchant.customers.index'));

    $response->assertOk();
    $response->assertSee('Store A1');
    $response->assertSee('Store A2');
    $response->assertSee('customer1@example.com');
    $response->assertSee('customer2@example.com');
    $response->assertSee('customer3@example.com');
});

test('store filter works', function () {
    $userA = User::factory()->create();

    $storeA1 = Store::factory()->create(['user_id' => $userA->id, 'name' => 'Store A1']);
    $storeA2 = Store::factory()->create(['user_id' => $userA->id, 'name' => 'Store A2']);

    $customer1 = Customer::factory()->create(['email' => 'customer1@example.com']);
    $customer2 = Customer::factory()->create(['email' => 'customer2@example.com']);

    $account1 = LoyaltyAccount::factory()->create([
        'store_id' => $storeA1->id,
        'customer_id' => $customer1->id,
    ]);

    $account2 = LoyaltyAccount::factory()->create([
        'store_id' => $storeA2->id,
        'customer_id' => $customer2->id,
    ]);

    $response = $this->actingAs($userA)->get(route('merchant.customers.index', ['store_id' => $storeA1->id]));

    $response->assertOk();
    $response->assertSee('customer1@example.com');
    $response->assertDontSee('customer2@example.com');
});

test('merchant cannot filter to another merchant store', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $storeA1 = Store::factory()->create(['user_id' => $userA->id]);
    $storeB1 = Store::factory()->create(['user_id' => $userB->id]);

    $response = $this->actingAs($userA)->get(route('merchant.customers.index', ['store_id' => $storeB1->id]));

    $response->assertNotFound();
});

test('merchant cannot view other merchant loyalty account detail', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    // Give userA a store so they can access the route
    $storeA1 = Store::factory()->create(['user_id' => $userA->id]);
    $storeB1 = Store::factory()->create(['user_id' => $userB->id]);

    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $storeB1->id,
        'customer_id' => $customer->id,
    ]);

    $response = $this->actingAs($userA)->get(route('merchant.customers.show', $account));

    $response->assertRedirect(route('merchant.customers.index'));
    $response->assertSessionHasErrors('support');
});

test('search works by email', function () {
    $userA = User::factory()->create();

    $store = Store::factory()->create(['user_id' => $userA->id]);

    $customer1 = Customer::factory()->create(['email' => 'john@example.com', 'name' => 'John Doe']);
    $customer2 = Customer::factory()->create(['email' => 'jane@example.com', 'name' => 'Jane Doe']);

    $account1 = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer1->id,
    ]);

    $account2 = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer2->id,
    ]);

    $response = $this->actingAs($userA)->get(route('merchant.customers.index', ['q' => 'john']));

    $response->assertOk();
    $response->assertSee('john@example.com');
    $response->assertDontSee('jane@example.com');
});

test('search works by name', function () {
    $userA = User::factory()->create();

    $store = Store::factory()->create(['user_id' => $userA->id]);

    $customer1 = Customer::factory()->create(['name' => 'John Smith', 'email' => 'john@example.com']);
    $customer2 = Customer::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

    $account1 = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer1->id,
    ]);

    $account2 = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer2->id,
    ]);

    $response = $this->actingAs($userA)->get(route('merchant.customers.index', ['q' => 'John']));

    $response->assertOk();
    $response->assertSee('John Smith');
    $response->assertDontSee('Jane Smith');
});

test('search works by phone', function () {
    $userA = User::factory()->create();

    $store = Store::factory()->create(['user_id' => $userA->id]);

    $customer1 = Customer::factory()->create(['phone' => '1234567890', 'email' => 'john@example.com']);
    $customer2 = Customer::factory()->create(['phone' => '0987654321', 'email' => 'jane@example.com']);

    $account1 = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer1->id,
    ]);

    $account2 = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer2->id,
    ]);

    $response = $this->actingAs($userA)->get(route('merchant.customers.index', ['q' => '123456']));

    $response->assertOk();
    $response->assertSee('1234567890');
    $response->assertDontSee('0987654321');
});

test('merchant can view their own loyalty account detail', function () {
    $userA = User::factory()->create();

    $store = Store::factory()->create(['user_id' => $userA->id]);

    $customer = Customer::factory()->create(['name' => 'Test Customer', 'email' => 'test@example.com']);
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'stamp_count' => 5,
    ]);

    $response = $this->actingAs($userA)->get(route('merchant.customers.show', $account));

    $response->assertOk();
    $response->assertSee('Test Customer');
    $response->assertSee('test@example.com');
    $response->assertSee('5');
});

test('pro merchant can export customers as csv', function () {
    $user = User::factory()->create(['stripe_id' => 'sub_export']);
    Subscription::create([
        'user_id' => $user->id,
        'name' => 'default',
        'stripe_id' => 'si_export',
        'stripe_status' => 'active',
        'quantity' => 1,
    ]);

    $store = Store::factory()->create(['user_id' => $user->id, 'name' => 'Export Store']);
    $customer = Customer::factory()->create([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'phone' => '0211234567',
    ]);
    LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'manual_entry_code' => 'A3CX',
        'stamp_count' => 4,
    ]);

    $response = $this->actingAs($user)->get(route('merchant.customers.export'));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $csv = $response->streamedContent();

    expect($csv)->toContain('Ada Lovelace')
        ->and($csv)->toContain('ada@example.com')
        ->and($csv)->toContain('Export Store')
        ->and($csv)->toContain('A3CX');
});

test('free merchant cannot export customers as csv', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::factory()->create(['email' => 'free@example.com']);
    LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
    ]);

    $response = $this->actingAs($user)->get(route('merchant.customers.export'));

    $response->assertRedirect(route('billing.index'));
    $response->assertSessionHasErrors('billing');
});

test('customer export respects current filters', function () {
    $user = User::factory()->create(['stripe_id' => 'sub_filter']);
    Subscription::create([
        'user_id' => $user->id,
        'name' => 'default',
        'stripe_id' => 'si_filter',
        'stripe_status' => 'active',
        'quantity' => 1,
    ]);

    $storeA = Store::factory()->create(['user_id' => $user->id, 'name' => 'Store A']);
    $storeB = Store::factory()->create(['user_id' => $user->id, 'name' => 'Store B']);

    $customerA = Customer::factory()->create(['email' => 'included@example.com', 'name' => 'Included Customer']);
    $customerB = Customer::factory()->create(['email' => 'excluded@example.com', 'name' => 'Excluded Customer']);

    LoyaltyAccount::factory()->create([
        'store_id' => $storeA->id,
        'customer_id' => $customerA->id,
    ]);
    LoyaltyAccount::factory()->create([
        'store_id' => $storeB->id,
        'customer_id' => $customerB->id,
    ]);

    $response = $this->actingAs($user)->get(route('merchant.customers.export', [
        'store_id' => $storeA->id,
        'q' => 'included',
    ]));

    $response->assertOk();
    $csv = $response->streamedContent();
    expect($csv)->toContain('included@example.com')
        ->and($csv)->not->toContain('excluded@example.com');
});

test('customers page shows pro export action for subscribed merchants', function () {
    $user = User::factory()->create(['stripe_id' => 'sub_ui']);
    Subscription::create([
        'user_id' => $user->id,
        'name' => 'default',
        'stripe_id' => 'si_ui',
        'stripe_status' => 'active',
        'quantity' => 1,
    ]);
    Store::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('merchant.customers.index'))
        ->assertOk()
        ->assertSee('Export CSV')
        ->assertDontSee('Export CSV (Pro)');
});

test('customers page shows upgrade prompt for free merchants', function () {
    $user = User::factory()->create();
    Store::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('merchant.customers.index'))
        ->assertOk()
        ->assertSee('Export CSV (Pro)')
        ->assertSee('Upgrade to Pro to export customer details.');
});
