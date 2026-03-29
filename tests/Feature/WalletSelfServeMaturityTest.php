<?php

namespace Tests\Feature;

use App\Models\AppleWalletRegistration;
use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\SupportAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletSelfServeMaturityTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_qr_page_shows_wallet_health_panel(): void
    {
        $merchant = User::factory()->create();
        $store = Store::factory()->for($merchant)->create();
        $customer = Customer::factory()->create();
        $account = LoyaltyAccount::factory()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
        ]);

        AppleWalletRegistration::create([
            'device_library_identifier' => 'device-1',
            'push_token' => 'push-token',
            'pass_type_identifier' => 'pass.kawhe.test',
            'serial_number' => 'serial-1',
            'loyalty_account_id' => $account->id,
            'active' => true,
            'last_registered_at' => now(),
        ]);

        SupportAuditLog::create([
            'store_id' => $store->id,
            'loyalty_account_id' => $account->id,
            'event_type' => 'wallet_sync',
            'status' => 'success',
            'source' => 'test',
            'message' => 'Wallet sync completed.',
        ]);

        $response = $this->actingAs($merchant)->get(route('merchant.stores.qr', $store));

        $response->assertOk();
        $response->assertSee('Wallet Health');
        $response->assertSee('Apple registrations');
        $response->assertSee('Recent wallet sync attempts');
    }
}
