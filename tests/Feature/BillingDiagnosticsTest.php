<?php

namespace Tests\Feature;

use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_page_shows_support_diagnostics_panel(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('billing.index'));

        $response->assertOk();
        $response->assertSee('Billing Support Diagnostics');
        $response->assertSee('Stripe customer linked');
        $response->assertSee('Plan allows another loyalty card');
        $response->assertSee('Recommended next step');
        $response->assertSee('Plan State');
        $response->assertSee('Recovery actions');
    }

    public function test_billing_page_surfaces_blocking_card_limit_state(): void
    {
        $user = User::factory()->create();
        Store::factory()->for($user)->create();

        $response = $this
            ->actingAs($user)
            ->get(route('billing.index'));

        $response->assertOk();
        $response->assertSee('You have used all free plan card slots');
        $response->assertSee('Sync Billing Status');
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
