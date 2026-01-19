<?php

use App\Models\AppleWalletRegistration;
use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Set up test configuration
    Config::set('passgenerator.pass_type_identifier', 'pass.com.kawhe.loyalty');
    Config::set('wallet.apple.web_service_auth_token', 'test-auth-token-123');
});

test('register device creates new registration', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
    ]);

    $serialNumber = "kawhe-{$store->id}-{$customer->id}";
    $deviceId = 'test-device-123';
    $pushToken = 'test-push-token-456';

    $response = $this->postJson("/wallet/v1/devices/{$deviceId}/registrations/pass.com.kawhe.loyalty/{$serialNumber}", [
        'pushToken' => $pushToken,
    ], [
        'Authorization' => 'ApplePass test-auth-token-123',
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('apple_wallet_registrations', [
        'device_library_identifier' => $deviceId,
        'push_token' => $pushToken,
        'pass_type_identifier' => 'pass.com.kawhe.loyalty',
        'serial_number' => $serialNumber,
        'loyalty_account_id' => $account->id,
        'active' => true,
    ]);
});

test('register device again is idempotent', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
    ]);

    $serialNumber = "kawhe-{$store->id}-{$customer->id}";
    $deviceId = 'test-device-123';
    $pushToken = 'test-push-token-456';

    // First registration
    $response1 = $this->postJson("/wallet/v1/devices/{$deviceId}/registrations/pass.com.kawhe.loyalty/{$serialNumber}", [
        'pushToken' => $pushToken,
    ], [
        'Authorization' => 'ApplePass test-auth-token-123',
    ]);

    $response1->assertStatus(201);

    // Second registration with same data
    $response2 = $this->postJson("/wallet/v1/devices/{$deviceId}/registrations/pass.com.kawhe.loyalty/{$serialNumber}", [
        'pushToken' => $pushToken,
    ], [
        'Authorization' => 'ApplePass test-auth-token-123',
    ]);

    $response2->assertStatus(200); // Should return 200 for existing registration

    // Should still have only one registration
    $this->assertDatabaseCount('apple_wallet_registrations', 1);
});

test('unregister device deactivates registration', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
    ]);

    $serialNumber = "kawhe-{$store->id}-{$customer->id}";
    $deviceId = 'test-device-123';

    // Create registration
    AppleWalletRegistration::create([
        'device_library_identifier' => $deviceId,
        'push_token' => 'test-token',
        'pass_type_identifier' => 'pass.com.kawhe.loyalty',
        'serial_number' => $serialNumber,
        'loyalty_account_id' => $account->id,
        'active' => true,
    ]);

    // Unregister
    $response = $this->delete("/wallet/v1/devices/{$deviceId}/registrations/pass.com.kawhe.loyalty/{$serialNumber}", [], [
        'Authorization' => 'ApplePass test-auth-token-123',
    ]);

    $response->assertStatus(200);

    $this->assertDatabaseHas('apple_wallet_registrations', [
        'device_library_identifier' => $deviceId,
        'active' => false,
    ]);
});

test('get pass returns 200 and correct content type', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
    ]);

    $serialNumber = "kawhe-{$store->id}-{$customer->id}";

    $response = $this->get("/wallet/v1/passes/pass.com.kawhe.loyalty/{$serialNumber}", [
        'Authorization' => 'ApplePass test-auth-token-123',
    ]);

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/vnd.apple.pkpass');
    $response->assertHeader('Cache-Control', 'no-store');
    
    // Verify it's a valid ZIP file (pkpass is a ZIP)
    $content = $response->getContent();
    expect(substr($content, 0, 2))->toBe('PK');
});

test('get pass returns 304 when if-modified-since is newer', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'updated_at' => now()->subHour(),
    ]);

    $serialNumber = "kawhe-{$store->id}-{$customer->id}";
    $ifModifiedSince = now()->toRfc7231String();

    $response = $this->get("/wallet/v1/passes/pass.com.kawhe.loyalty/{$serialNumber}", [
        'Authorization' => 'ApplePass test-auth-token-123',
        'If-Modified-Since' => $ifModifiedSince,
    ]);

    $response->assertStatus(304);
    $response->assertHeader('Last-Modified');
});

test('get pass returns 404 for invalid serial number', function () {
    $response = $this->get('/wallet/v1/passes/pass.com.kawhe.loyalty/invalid-serial', [
        'Authorization' => 'ApplePass test-auth-token-123',
    ]);

    $response->assertStatus(404);
});

test('get updated serials returns correct format', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id]);
    $customer = Customer::factory()->create();
    $account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'customer_id' => $customer->id,
    ]);

    $serialNumber = "kawhe-{$store->id}-{$customer->id}";
    $deviceId = 'test-device-123';

    AppleWalletRegistration::create([
        'device_library_identifier' => $deviceId,
        'push_token' => 'test-token',
        'pass_type_identifier' => 'pass.com.kawhe.loyalty',
        'serial_number' => $serialNumber,
        'loyalty_account_id' => $account->id,
        'active' => true,
    ]);

    $response = $this->get("/wallet/v1/devices/{$deviceId}/registrations/pass.com.kawhe.loyalty", [
        'Authorization' => 'ApplePass test-auth-token-123',
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'lastUpdated',
        'serialNumbers',
    ]);
    
    $data = $response->json();
    expect($data['serialNumbers'])->toContain($serialNumber);
});

test('log endpoint accepts logs and returns 200', function () {
    $response = $this->postJson('/wallet/v1/log', [
        'logs' => [
            [
                'level' => 'info',
                'message' => 'Test log message',
            ],
        ],
    ], [
        'Authorization' => 'ApplePass test-auth-token-123',
    ]);

    $response->assertStatus(200);
});

test('authentication fails without valid token', function () {
    $response = $this->postJson('/wallet/v1/log', [
        'logs' => [],
    ]);

    $response->assertStatus(401);
});

test('authentication fails with invalid token', function () {
    $response = $this->postJson('/wallet/v1/log', [
        'logs' => [],
    ], [
        'Authorization' => 'ApplePass wrong-token',
    ]);

    $response->assertStatus(401);
});
