<?php

use App\Models\AppleWalletRegistration;
use App\Services\Wallet\Apple\ApplePushService;
use App\Services\Wallet\WalletPlatformException;
use Illuminate\Support\Facades\Storage;

function walletPushOptions(): array
{
    return (new ReflectionMethod(ApplePushService::class, 'pushOptions'))->invoke(
        app(ApplePushService::class), new AppleWalletRegistration(['push_token' => str_repeat('a', 64)])
    );
}

beforeEach(function () {
    Storage::fake('wallet-cert-test');
    config(['passgenerator.config_disk' => 'wallet-cert-test', 'passgenerator.certificate_store_path' => 'identity.p12', 'passgenerator.certificate_store_password' => 'test-password', 'passgenerator.pass_type_identifier' => 'pass.test.wallet']);
});

test('wallet push uses signing certificate empty payload and production endpoint without app keys', function () {
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $csr = openssl_csr_new(['commonName' => 'Wallet transport test'], $key);
    $cert = openssl_csr_sign($csr, null, $key, 1);
    openssl_pkcs12_export($cert, $p12, $key, 'test-password');
    Storage::disk('wallet-cert-test')->put('identity.p12', $p12);
    config(['wallet.apple.apns_key_id' => null, 'wallet.apple.apns_team_id' => null, 'wallet.apple.apns_auth_key_path' => null, 'wallet.apple.apns_production' => false]);

    $options = walletPushOptions();
    expect($options[CURLOPT_POSTFIELDS])->toBe('{}')
        ->and($options[CURLOPT_URL])->toBe('https://api.push.apple.com/3/device/'.str_repeat('a', 64))
        ->and($options[CURLOPT_HTTPHEADER])->toContain('apns-topic: pass.test.wallet')
        ->and(implode('\n', $options[CURLOPT_HTTPHEADER]))->not->toContain('Authorization:', 'apns-push-type: background')
        ->and(openssl_x509_check_private_key($options[CURLOPT_SSLCERT_BLOB], $options[CURLOPT_SSLKEY_BLOB]))->toBeTrue()
        ->and($options[CURLOPT_SSL_VERIFYPEER])->toBeTrue()
        ->and($options[CURLOPT_TIMEOUT])->toBeLessThan(60);
});

test('wallet push fails safely when signing identity is missing', function () {
    expect(fn () => walletPushOptions())->toThrow(WalletPlatformException::class, 'signing certificate is missing');
});

test('wallet push fails safely for an unreadable signing identity', function () {
    Storage::disk('wallet-cert-test')->put('identity.p12', 'invalid');
    expect(fn () => walletPushOptions())->toThrow(WalletPlatformException::class, 'could not be opened');
});
