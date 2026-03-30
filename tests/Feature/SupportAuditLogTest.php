<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\SupportAuditLog;
use App\Models\User;
use App\Services\Support\SupportAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_audit_service_persists_logs(): void
    {
        $merchant = User::factory()->create();
        $store = Store::factory()->for($merchant)->create();
        $customer = Customer::factory()->create();
        $account = LoyaltyAccount::factory()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
        ]);

        app(SupportAuditService::class)->log(
            eventType: 'wallet_sync',
            status: 'success',
            storeId: $store->id,
            loyaltyAccountId: $account->id,
            actorUserId: $merchant->id,
            source: 'test',
            message: 'Wallet sync completed from test.',
            metadata: ['channel' => 'test']
        );

        $this->assertDatabaseHas('support_audit_logs', [
            'event_type' => 'wallet_sync',
            'status' => 'success',
            'store_id' => $store->id,
            'loyalty_account_id' => $account->id,
            'actor_user_id' => $merchant->id,
        ]);
    }

    public function test_admin_dashboard_shows_support_diagnostics_sections(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $merchant = User::factory()->create();
        $store = Store::factory()->for($merchant)->create();

        SupportAuditLog::create([
            'store_id' => $store->id,
            'event_type' => 'billing_issue',
            'status' => 'failed',
            'source' => 'test',
            'message' => 'Billing sync failed.',
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Merchant Issue Diagnostics');
        $response->assertSee('Recent Activity');
    }
}
