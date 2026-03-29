<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreArchiveFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_can_archive_store_and_restore_it(): void
    {
        $merchant = User::factory()->create();
        $store = Store::factory()->for($merchant)->create();
        $customer = Customer::factory()->create();

        LoyaltyAccount::factory()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
        ]);

        $archiveResponse = $this
            ->actingAs($merchant)
            ->delete(route('merchant.stores.destroy', $store));

        $archiveResponse->assertRedirect(route('merchant.stores.index'));
        $archiveResponse->assertSessionHas('success');

        $store->refresh();

        $this->assertSoftDeleted('stores', ['id' => $store->id]);
        $this->assertDatabaseCount('loyalty_accounts', 1);

        $this->get(route('join.index', ['slug' => $store->slug, 't' => $store->join_token]))
            ->assertOk()
            ->assertSee('Store temporarily unavailable');

        $restoreResponse = $this
            ->actingAs($merchant)
            ->post(route('merchant.stores.restore', $store->fresh()));

        $restoreResponse->assertRedirect(route('merchant.stores.edit', $store));
        $restoreResponse->assertSessionHas('success');

        $this->assertDatabaseHas('stores', [
            'id' => $store->id,
            'deleted_at' => null,
        ]);

        $this->get(route('join.index', ['slug' => $store->slug, 't' => $store->join_token]))
            ->assertOk();
    }
}
