<?php

use App\Models\LoyaltyAccount;
use App\Models\LoyaltyProgram;
use App\Services\Wallet\Apple\ApplePushService;
use App\Services\Wallet\Apple\AppleWalletSerial;
use App\Services\Wallet\GoogleWalletPassService;
use App\Services\Wallet\WalletIdentity;

beforeEach(function () {
    config(['wallet.test_identity_namespace' => null, 'app.env' => 'testing', 'app.url' => 'https://testing.kawhe.shop']);
});

test('testing rejects production and legacy Apple serials and resolves its own', function () {
    $a = LoyaltyAccount::factory()->create();
    $serial = AppleWalletSerial::fromAccount($a);
    expect($serial)->toStartWith('env_');
    expect(AppleWalletSerial::resolveAccount($serial)?->id)->toBe($a->id);
    expect(AppleWalletSerial::parse('kawhe-'.$a->id))->toBeNull();
    expect(AppleWalletSerial::parse('kawhe-'.$a->store_id.'-'.$a->customer_id))->toBeNull();
    config(['app.url' => 'https://another-testing.kawhe.shop']);
    expect(AppleWalletSerial::parse($serial))->toBeNull();
});

test('production keeps existing Apple identity and rejects testing identity', function () {
    $a = LoyaltyAccount::factory()->create();
    $testing = AppleWalletSerial::fromAccount($a);
    config(['app.env' => 'production', 'app.url' => 'https://app.kawhe.shop']);
    expect(AppleWalletSerial::fromAccount($a))->toBe('kawhe-'.$a->id);
    expect(AppleWalletSerial::resolveAccount('kawhe-'.$a->id)?->id)->toBe($a->id);
    expect(AppleWalletSerial::parse($testing))->toBeNull();
});

test('all Google resource identifiers separate testing from production', function () {
    $service = (new ReflectionClass(GoogleWalletPassService::class))->newInstanceWithoutConstructor();
    (new ReflectionProperty($service, 'issuerId'))->setValue($service, 'issuer');
    $a = new LoyaltyAccount;
    $a->id = 42;
    $p = new LoyaltyProgram;
    $p->id = 42;
    foreach (['getClassIdForProgram' => $p, 'getGenericClassIdForProgram' => $p, 'getObjectIdForAccount' => $a, 'getGenericObjectIdForAccount' => $a] as $method => $model) {
        config(['app.env' => 'testing', 'app.url' => 'https://testing.kawhe.shop']);
        $testing = (new ReflectionMethod($service, $method))->invoke($service, $model);
        expect($testing)->toContain(WalletIdentity::namespace());
        config(['app.env' => 'production', 'app.url' => 'https://app.kawhe.shop']);
        $production = (new ReflectionMethod($service, $method))->invoke($service, $model);
        expect($production)->not->toContain('env_');
        expect($testing)->not->toBe($production);
    }
});

test('testing push rejects old serials before selecting registrations or sending', function () {
    config(['wallet.apple.push_enabled' => true]);
    $result = app(ApplePushService::class)->sendPassUpdatePushes(config('passgenerator.pass_type_identifier'), 'kawhe-42');
    expect($result)->toBe(['status' => 'environment_mismatch', 'registrations' => 0, 'sent' => 0]);
});

test('isolated Apple service rejects numeric and public token fallback identities', function () {
    $a = LoyaltyAccount::factory()->create();
    $service = (new ReflectionClass(\App\Services\Wallet\Apple\ApplePassService::class))->newInstanceWithoutConstructor();
    expect($service->resolveLoyaltyAccount((string) $a->id))->toBeNull();
    expect($service->resolveLoyaltyAccount($a->public_token))->toBeNull();
    expect($service->resolveLoyaltyAccount(AppleWalletSerial::fromAccount($a))?->id)->toBe($a->id);
});

test('testing device update list excludes copied live registrations', function () {
    $a = LoyaltyAccount::factory()->create();
    $type = config('passgenerator.pass_type_identifier');
    $serial = AppleWalletSerial::fromAccount($a);
    foreach (['kawhe-'.$a->id, $serial] as $s) {
        \App\Models\AppleWalletRegistration::create(['device_library_identifier' => 'isolation-device', 'push_token' => 'fake-push', 'pass_type_identifier' => $type, 'serial_number' => $s, 'loyalty_account_id' => $a->id, 'active' => true]);
    }
    $this->getJson('/wallet/v1/devices/isolation-device/registrations/'.$type)->assertOk()->assertJsonPath('serialNumbers', [$serial]);
});

test('testing cannot retrieve a live serial even with valid account credentials', function () {
    $a = LoyaltyAccount::factory()->create();
    $this->get('/wallet/v1/passes/'.config('passgenerator.pass_type_identifier').'/kawhe-'.$a->id, ['Authorization' => 'ApplePass '.$a->wallet_auth_token])->assertUnauthorized();
});
