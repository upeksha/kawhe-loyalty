<?php

namespace Tests\Feature;

use App\Jobs\UpdateWalletPassJob;
use App\Mail\CustomerWelcomeEmail;
use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MerchantRecoveryToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_can_resend_customer_welcome_email(): void
    {
        Mail::fake();

        $merchant = User::factory()->create();
        $store = Store::factory()->for($merchant)->create();
        $customer = Customer::factory()->create([
            'email' => 'welcome@example.com',
        ]);

        $account = LoyaltyAccount::factory()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
        ]);

        $response = $this
            ->actingAs($merchant)
            ->post(route('merchant.customers.resend-welcome', $account));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Mail::assertQueued(CustomerWelcomeEmail::class);

        $this->assertDatabaseHas('support_audit_logs', [
            'event_type' => 'welcome_email_send',
            'status' => 'success',
            'store_id' => $store->id,
            'loyalty_account_id' => $account->id,
        ]);
    }

    public function test_merchant_can_queue_store_wallet_refresh(): void
    {
        Queue::fake();

        $merchant = User::factory()->create();
        $store = Store::factory()->for($merchant)->create();

        LoyaltyAccount::factory()->count(2)->create([
            'store_id' => $store->id,
        ]);

        $response = $this
            ->actingAs($merchant)
            ->post(route('merchant.stores.refresh-wallets', $store));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Queue::assertPushed(UpdateWalletPassJob::class, 2);

        $this->assertDatabaseHas('support_audit_logs', [
            'event_type' => 'store_wallet_refresh',
            'status' => 'success',
            'store_id' => $store->id,
        ]);
    }
}
