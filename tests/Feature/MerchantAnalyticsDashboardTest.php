<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\PointsTransaction;
use App\Models\StampEvent;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantAnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_dashboard_shows_value_metrics(): void
    {
        $merchant = User::factory()->create();
        $store = Store::factory()->for($merchant)->create();

        $activeAccount = LoyaltyAccount::factory()->create([
            'store_id' => $store->id,
            'customer_id' => Customer::factory()->create()->id,
            'created_at' => now()->subDays(5),
            'last_stamped_at' => now()->subDays(2),
        ]);

        $rewardAccount = LoyaltyAccount::factory()->create([
            'store_id' => $store->id,
            'customer_id' => Customer::factory()->create()->id,
            'created_at' => now()->subDays(20),
            'reward_available_at' => now()->subDays(10),
        ]);

        LoyaltyAccount::factory()->create([
            'store_id' => $store->id,
            'customer_id' => Customer::factory()->create()->id,
            'created_at' => now()->subDays(45),
            'last_stamped_at' => now()->subDays(45),
        ]);

        PointsTransaction::create([
            'loyalty_account_id' => $rewardAccount->id,
            'store_id' => $store->id,
            'user_id' => $merchant->id,
            'type' => 'earn',
            'points' => 5,
            'idempotency_key' => 'analytics-earn',
            'metadata' => ['newly_earned_rewards' => 1],
        ]);

        PointsTransaction::create([
            'loyalty_account_id' => $rewardAccount->id,
            'store_id' => $store->id,
            'user_id' => $merchant->id,
            'type' => 'redeem',
            'points' => -5,
            'idempotency_key' => 'analytics-redeem',
            'metadata' => ['rewards_redeemed' => 1],
        ]);

        StampEvent::create([
            'loyalty_account_id' => $activeAccount->id,
            'store_id' => $store->id,
            'user_id' => $merchant->id,
            'type' => 'stamp',
            'count' => 3,
            'idempotency_key' => 'analytics-stamp',
        ]);

        StampEvent::create([
            'loyalty_account_id' => $rewardAccount->id,
            'store_id' => $store->id,
            'user_id' => $merchant->id,
            'type' => 'redeem',
            'count' => 1,
            'idempotency_key' => 'analytics-redeem-event',
        ]);

        $response = $this
            ->actingAs($merchant)
            ->get(route('merchant.dashboard'));

        $response->assertOk();
        $response->assertSee('Merchant Analytics');
        $response->assertSee('Recent Activity Trend');
        $response->assertSee('Active customers');
        $response->assertSee('2');
        $response->assertSee('Joins over time');
        $response->assertSee('Rewards earned');
        $response->assertSee('Rewards redeemed');
        $response->assertSee('Mar');
    }
}
