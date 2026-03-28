<?php

namespace Tests\Feature;

use App\Jobs\UpdateWalletPassJob;
use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MerchantCustomersSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_can_search_customers_by_manual_code(): void
    {
        $merchant = User::factory()->create();
        $store = Store::factory()->for($merchant)->create();
        $customer = Customer::factory()->create([
            'name' => 'Jordan Support',
            'email' => 'jordan@example.com',
        ]);

        $account = LoyaltyAccount::factory()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'manual_entry_code' => 'A3CX',
        ]);

        $response = $this
            ->actingAs($merchant)
            ->get(route('merchant.customers.index', ['q' => 'A3CX']));

        $response->assertOk();
        $response->assertSee('Jordan Support');
        $response->assertSee($account->manual_entry_code);
    }

    public function test_customer_support_snapshot_is_visible_on_customer_details(): void
    {
        $merchant = User::factory()->create();
        $store = Store::factory()->for($merchant)->create();
        $customer = Customer::factory()->create();

        $account = LoyaltyAccount::factory()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'manual_entry_code' => 'Z9KP',
        ]);

        $response = $this
            ->actingAs($merchant)
            ->get(route('merchant.customers.show', $account));

        $response->assertOk();
        $response->assertSee('Support Snapshot');
        $response->assertSee('Z9KP');
        $response->assertSee($account->public_token);
        $response->assertSee('Recent Support Timeline');
    }

    public function test_merchant_can_queue_wallet_refresh_from_customer_details(): void
    {
        Queue::fake();

        $merchant = User::factory()->create();
        $store = Store::factory()->for($merchant)->create();
        $customer = Customer::factory()->create();

        $account = LoyaltyAccount::factory()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
        ]);

        $response = $this
            ->actingAs($merchant)
            ->post(route('merchant.customers.sync-wallet', $account));

        $response->assertRedirect();
        Queue::assertPushed(UpdateWalletPassJob::class, fn ($job) => $job->loyaltyAccountId === $account->id);
    }
}
