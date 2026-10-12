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

        $this->get(route('merchant.stores.index'))->assertOk()->assertSee($store->name);

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

    public function test_merchant_with_only_archived_stores_cannot_restore_another_merchants_store(): void
    {
        $merchant = User::factory()->create();
        Store::factory()->for($merchant)->create(['deleted_at' => now()]);
        $otherStore = Store::factory()->create(['deleted_at' => now()]);

        $this->actingAs($merchant)->post(route('merchant.stores.restore', $otherStore))->assertNotFound();
        $this->assertSoftDeleted($otherStore);
    }

    public function test_new_merchant_still_goes_to_onboarding(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('merchant.stores.index'))
            ->assertRedirect(route('merchant.onboarding.wizard.store-basics'));
    }
}
