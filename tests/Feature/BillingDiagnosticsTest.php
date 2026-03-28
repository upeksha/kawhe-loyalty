<?php

namespace Tests\Feature;

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
        $response->assertSee('Plan allows new joins');
    }
}
