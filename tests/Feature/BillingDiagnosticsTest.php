<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_page_shows_usage_and_plan_comparison(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('billing.index'));

        $response->assertOk();
        $response->assertSee('Usage right now');
        $response->assertSee('% used');
        $response->assertSee('Plan State');
        $response->assertSee('Compare plans');
        $response->assertSee('Business');
        $response->assertSee('Coming soon');
        $response->assertDontSee('Advanced billing help');
        $response->assertDontSee('Sync Billing Status');
    }

    public function test_billing_page_shows_free_plan_active_for_new_merchant_with_store(): void
    {
        $user = User::factory()->create();
        Store::factory()->for($user)->create();

        $response = $this
            ->actingAs($user)
            ->get(route('billing.index'));

        $response->assertOk();
        $response->assertSee('Free plan active');
        $response->assertSee('You are on the free plan');
        $response->assertDontSee('You have reached the free customer limit');
    }

    public function test_billing_page_surfaces_customer_limit_state(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->for($user)->create();
        $program = $store->loyaltyPrograms()->first();

        for ($i = 0; $i < 100; $i++) {
            \App\Models\LoyaltyAccount::create([
                'store_id' => $store->id,
                'loyalty_program_id' => $program->id,
                'customer_id' => \App\Models\Customer::factory()->create()->id,
            ]);
        }

        $response = $this
            ->actingAs($user)
            ->get(route('billing.index'));

        $response->assertOk();
        $response->assertSee('Free plan full');
        $response->assertSee('You have reached the free customer limit');
        $response->assertDontSee('Sync Billing Status');
    }

    public function test_billing_cancel_page_explains_next_steps(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('billing.cancel'));

        $response->assertOk();
        $response->assertSee('Checkout Cancelled');
        $response->assertSee('What this means');
        $response->assertSee('No charges were made');
    }

    public function test_billing_success_page_shows_guidance_when_missing_session(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('billing.success'));

        $response->assertOk();
        $response->assertSee('Issue Detected');
        $response->assertSee('What to do next');
    }
}
