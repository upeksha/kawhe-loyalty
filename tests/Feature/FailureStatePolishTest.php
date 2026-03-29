<?php

namespace Tests\Feature;

use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FailureStatePolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_card_shows_friendly_unavailable_page(): void
    {
        $response = $this->get('/c/not-a-real-token');

        $response->assertNotFound();
        $response->assertSee('Card not found');
        $response->assertSee('ask the store to help you recover your card');
    }

    public function test_merchant_redirects_to_customer_list_when_supporting_foreign_card(): void
    {
        $merchant = User::factory()->create();
        Store::factory()->for($merchant)->create();
        $otherMerchant = User::factory()->create();
        $store = Store::factory()->for($otherMerchant)->create();
        $account = LoyaltyAccount::factory()->create([
            'store_id' => $store->id,
        ]);

        $response = $this
            ->actingAs($merchant)
            ->get(route('merchant.customers.show', $account));

        $response->assertRedirect(route('merchant.customers.index'));
        $response->assertSessionHasErrors('support');
    }
}
