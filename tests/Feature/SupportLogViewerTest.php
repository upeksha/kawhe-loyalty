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
    }
}
