<?php

use App\Jobs\UpdateWalletPassJob;
use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\SupportAuditLog;
use App\Models\User;
use App\Services\Wallet\Apple\ApplePushService;
use App\Services\Wallet\GoogleWalletPassService;
use App\Services\Wallet\WalletPlatformException;
use App\Services\Wallet\WalletSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $store = Store::factory()->create(['user_id' => User::factory()]);
    $this->account = LoyaltyAccount::factory()->create([
        'store_id' => $store->id,
        'loyalty_program_id' => $store->resolvedDefaultProgram()->id,
        'customer_id' => Customer::factory(),
    ]);
    config(['services.google_wallet.pass_type' => 'loyalty']);
});

test('temporary Google failure does not prevent Apple and requests a retry', function () {
    $apple = Mockery::mock(ApplePushService::class);
    $apple->shouldReceive('sendPassUpdatePushes')->once()->andReturn([
        'status' => 'success',
        'registrations' => 1,
        'sent' => 1,
    ]);
    $google = Mockery::mock(GoogleWalletPassService::class);
    $google->shouldReceive('createOrUpdateLoyaltyObject')->once()->andThrow(
        new WalletPlatformException('Temporary Google outage.', 'google_object', true, 503)
    );
    app()->instance(ApplePushService::class, $apple);
    app()->instance(GoogleWalletPassService::class, $google);

    expect(fn () => app(WalletSyncService::class)->syncLoyaltyAccount($this->account))
        ->toThrow(WalletPlatformException::class);

    $log = SupportAuditLog::where('event_type', 'wallet_sync')->latest()->first();
    expect($log->status)->toBe('partial')
        ->and(data_get($log->metadata, 'apple.status'))->toBe('success')
        ->and(data_get($log->metadata, 'google.retryable'))->toBeTrue();
});

test('temporary Apple failure does not prevent Google and requests a retry', function () {
    $apple = Mockery::mock(ApplePushService::class);
    $apple->shouldReceive('sendPassUpdatePushes')->once()->andThrow(
        new WalletPlatformException('Temporary APNs outage.', 'apple_push', true, 503)
    );
    $google = Mockery::mock(GoogleWalletPassService::class);
    $google->shouldReceive('createOrUpdateLoyaltyObject')->once()->andReturn(new stdClass);
    app()->instance(ApplePushService::class, $apple);
    app()->instance(GoogleWalletPassService::class, $google);

    expect(fn () => app(WalletSyncService::class)->syncLoyaltyAccount($this->account))
        ->toThrow(WalletPlatformException::class);

    $log = SupportAuditLog::where('event_type', 'wallet_sync')->latest()->first();
    expect(data_get($log->metadata, 'apple.retryable'))->toBeTrue()
        ->and(data_get($log->metadata, 'google.status'))->toBe('success');
});

test('Google class and object outcomes are recorded separately when available', function () {
    $apple = Mockery::mock(ApplePushService::class);
    $apple->shouldReceive('sendPassUpdatePushes')->once()->andReturn([
        'status' => 'success',
        'registrations' => 0,
        'sent' => 0,
    ]);
    $google = Mockery::mock(GoogleWalletPassService::class);
    $google->shouldReceive('createOrUpdateLoyaltyObject')->once()->andReturn(new stdClass);
    $google->shouldReceive('getLastSyncDetails')->once()->andReturn([
        'class' => ['status' => 'updated'],
        'object' => ['status' => 'updated'],
    ]);
    app()->instance(ApplePushService::class, $apple);
    app()->instance(GoogleWalletPassService::class, $google);

    app(WalletSyncService::class)->syncLoyaltyAccount($this->account);

    $log = SupportAuditLog::where('event_type', 'wallet_sync')->latest()->first();
    expect(data_get($log->metadata, 'google.class.status'))->toBe('updated')
        ->and(data_get($log->metadata, 'google.object.status'))->toBe('updated');
});

test('permanent configuration failures are logged without indefinite retries', function () {
    $apple = Mockery::mock(ApplePushService::class);
    $apple->shouldReceive('sendPassUpdatePushes')->once()->andReturn([
        'status' => 'not_configured',
        'registrations' => 0,
        'sent' => 0,
    ]);
    $google = Mockery::mock(GoogleWalletPassService::class);
    $google->shouldReceive('createOrUpdateLoyaltyObject')->once()->andThrow(
        new WalletPlatformException('Credentials missing.', 'credentials', false)
    );
    app()->instance(ApplePushService::class, $apple);
    app()->instance(GoogleWalletPassService::class, $google);

    app(WalletSyncService::class)->syncLoyaltyAccount($this->account);

    $log = SupportAuditLog::where('event_type', 'wallet_sync')->latest()->first();
    expect($log->status)->toBe('partial')
        ->and(data_get($log->metadata, 'google.category'))->toBe('credentials')
        ->and(data_get($log->metadata, 'google.retryable'))->toBeFalse();
});

test('wallet account jobs use bounded retries and overlap protection', function () {
    $job = new UpdateWalletPassJob($this->account->id);

    expect($job->tries)->toBe(5)
        ->and($job->timeout)->toBe(60)
        ->and($job->backoff())->toBe([30, 120, 300, 900])
        ->and($job->middleware())->toHaveCount(1);
});
