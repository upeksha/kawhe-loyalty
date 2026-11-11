<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\PointsTransaction;
use App\Models\StampEvent;
use App\Models\Store;
use App\Models\User;
use App\Services\Analytics\MerchantDashboardActions;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantAnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_dashboard_shows_value_metrics(): void
    {
        $this->travelTo(Carbon::parse('2026-03-20 12:00:00'));

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
        $response->assertSee('Loyalty Activity');
        $response->assertSee('Card Growth');
        $response->assertSee('Active customers');
        $response->assertSee('2');
        $response->assertSee('New cards');
        $response->assertSee('Rewards earned');
        $response->assertSee('Rewards redeemed');
        $response->assertSee('Mar');
    }

    public function test_dashboard_actions_follow_owned_customer_progress_and_plan_usage(): void
    {
        $owner = User::factory()->create();
        $store = Store::factory()->for($owner)->create(['reward_target' => 9]);
        $otherStore = Store::factory()->create(['reward_target' => 9]);

        $this->actingAs($owner)->get(route('merchant.dashboard'))->assertOk()
            ->assertSee('Display your join poster to get your first customer.')
            ->assertSee('Invite a staff member.');

        LoyaltyAccount::factory()->create([
            'store_id' => $store->id,
            'stamp_count' => 8,
            'created_at' => now()->subDays(10),
        ]);
        LoyaltyAccount::factory()->create([
            'store_id' => $otherStore->id,
            'stamp_count' => 8,
        ]);

        $this->actingAs($owner)->get(route('merchant.dashboard'))->assertOk()
            ->assertSee('1 customer is one stamp away.')
            ->assertSee('No activity this week—share your join link.')
            ->assertSee('Download this month’s customer activity.')
            ->assertDontSee('2 customers are one stamp away.')
            ->assertDontSee('Display your join poster to get your first customer.');

        $this->actingAs($owner)->get(route('merchant.customers.index', ['near_reward' => 1]))
            ->assertOk()->assertSee('Showing customer cards one stamp away from a reward.')
            ->assertSee('1 customer card matching your filters');
    }

    public function test_free_plan_capacity_prompt_uses_actual_limit_and_is_hidden_for_pro(): void
    {
        $owner = User::factory()->create();
        Store::factory()->for($owner)->create();
        $actions = app(MerchantDashboardActions::class);
        $free = $actions->forOwner($owner, [
            'primary_program_customers_count' => 78,
            'customers_per_program_limit' => 100,
            'is_subscribed' => false,
        ]);
        $pro = $actions->forOwner($owner, [
            'primary_program_customers_count' => 78,
            'customers_per_program_limit' => null,
            'is_subscribed' => true,
        ]);

        $this->assertContains('You are using 78 of 100 Free-plan customer places.', array_column($free, 'title'));
        $this->assertNotContains('You are using 78 of 100 Free-plan customer places.', array_column($pro, 'title'));
    }
}
