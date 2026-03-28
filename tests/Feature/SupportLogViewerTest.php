<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\SupportAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportLogViewerTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_can_view_support_log_page(): void
    {
        $merchant = User::factory()->create();
        $store = Store::factory()->for($merchant)->create();

        SupportAuditLog::create([
            'store_id' => $store->id,
            'event_type' => 'wallet_sync',
            'status' => 'success',
            'source' => 'test',
            'message' => 'Wallet sync completed.',
        ]);

        $response = $this
            ->actingAs($merchant)
            ->get(route('merchant.support.index'));

        $response->assertOk();
        $response->assertSee('Support activity across your stores');
        $response->assertSee('Wallet Sync');
        $response->assertSee('Matching events');
    }

    public function test_admin_can_view_support_log_page(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);

        SupportAuditLog::create([
            'event_type' => 'billing_issue',
            'status' => 'failed',
            'source' => 'test',
            'message' => 'Billing sync failed.',
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.support.index'));

        $response->assertOk();
        $response->assertSee('Global support event stream');
        $response->assertSee('Billing Issue');
        $response->assertSee('Failed events');
    }

    public function test_admin_can_filter_support_logs_by_store_and_search(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $merchant = User::factory()->create();
        $store = Store::factory()->for($merchant)->create(['name' => 'Filter Store']);

        SupportAuditLog::create([
            'store_id' => $store->id,
            'event_type' => 'wallet_sync',
            'status' => 'failed',
            'source' => 'test',
            'message' => 'Wallet failed.',
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.support.index', [
                'store_id' => $store->id,
                'q' => 'Filter Store',
            ]));

        $response->assertOk();
        $response->assertSee('Filter Store');
        $response->assertSee('Wallet failed.');
    }

    public function test_merchant_can_filter_support_logs_by_manual_code_search(): void
    {
        $merchant = User::factory()->create();
        $store = Store::factory()->for($merchant)->create();
        $customer = \App\Models\Customer::factory()->create([
            'email' => 'support@example.com',
        ]);
        $account = \App\Models\LoyaltyAccount::factory()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'manual_entry_code' => 'K7PL',
        ]);

        SupportAuditLog::create([
            'store_id' => $store->id,
            'loyalty_account_id' => $account->id,
            'event_type' => 'manual_support_action',
            'status' => 'success',
            'source' => 'merchant',
            'message' => 'Manual support action.',
        ]);

        $response = $this
            ->actingAs($merchant)
            ->get(route('merchant.support.index', ['q' => 'K7PL']));

        $response->assertOk();
        $response->assertSee('Manual Support Action');
        $response->assertSee('support@example.com');
    }
}
