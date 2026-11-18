<?php

use App\Jobs\RefreshProgramWalletsJob;
use App\Jobs\UpdateWalletPassJob;
use App\Models\AppleWalletRegistration;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Services\Support\SupportAuditService;
use App\Services\Wallet\Apple\AppleWalletSerial;
use App\Services\Wallet\AppleWalletPassService;
use App\Services\Wallet\Artwork\CardStyleArtwork;
use App\Services\Wallet\Artwork\WalletArtworkService;
use App\Services\Wallet\GoogleWalletPassService;
use App\Support\CardDesign;
use App\Support\CoffeeCardSample;
use App\Support\StoreAssets;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

function walletAppearanceTestCertificates(): array
{
    static $certificates;

    if ($certificates) {
        return $certificates;
    }

    // These disposable certificates exercise package signing without using Apple credentials.
    $rootKey = openssl_pkey_new(['private_key_bits' => 2048]);
    $rootCsr = openssl_csr_new(['commonName' => 'Kawhe local test CA'], $rootKey);
    $root = openssl_csr_sign($rootCsr, null, $rootKey, 1);
    $leafKey = openssl_pkey_new(['private_key_bits' => 2048]);
    $leafCsr = openssl_csr_new(['commonName' => 'Kawhe local test pass'], $leafKey);
    $leaf = openssl_csr_sign($leafCsr, $root, $rootKey, 1);
    openssl_pkcs12_export($leaf, $p12, $leafKey, 'local-test-only');
    openssl_x509_export($root, $pem);

    return $certificates = [$p12, $pem];
}

function walletAppearanceAccount(string $style, string $shape = 'square', string $rewardIcon = 'custom'): LoyaltyAccount
{
    $store = Store::factory()->create(['name' => 'TEST Wallet Appearance Cafe', 'reward_target' => 7]);
    $program = $store->resolvedDefaultProgram();
    $photo = UploadedFile::fake()->image('cafe.jpg', 900, 600);
    $icon = UploadedFile::fake()->image('reward.png', 96, 96);
    StoreAssets::put('card-designs/appearance-photo.jpg', file_get_contents($photo->getRealPath()));
    StoreAssets::put('card-designs/appearance-reward.png', file_get_contents($icon->getRealPath()));
    $program->update([
        'card_design' => $style, 'stamp_shape' => $shape, 'stamp_icon' => 'heart',
        'stamp_color' => '#FFD166', 'card_text_color' => '#FFF4CF', 'background_color' => '#263E30',
        'reward_icon' => $rewardIcon, 'reward_icon_path' => 'card-designs/appearance-reward.png',
        'card_photo_path' => $style === 'photo' ? 'card-designs/appearance-photo.jpg' : null,
        'require_verification_for_redemption' => false,
    ]);

    return LoyaltyAccount::factory()->create([
        'store_id' => $store->id, 'loyalty_program_id' => $program->id,
        'stamp_count' => 4, 'reward_balance' => 0, 'verified_at' => now(), 'last_stamped_at' => null,
    ]);
}

function walletAppearancePackage(string $binary): array
{
    $path = tempnam(sys_get_temp_dir(), 'kawhe-appearance-');
    file_put_contents($path, $binary);
    $zip = new ZipArchive;
    try {
        expect($zip->open($path))->toBeTrue();
        $files = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            $files[$name] = $zip->getFromName($name);
        }

        return $files;
    } finally {
        $zip->close();
        unlink($path);
    }
}

beforeEach(function () {
    config(['app.env' => 'testing', 'app.url' => 'https://testing.kawhe.shop', 'filesystems.assets_disk' => 'public']);
    Storage::fake('public');
    Storage::fake('appearance-signing');
    Storage::fake('appearance-passes');
    Queue::fake();
    [$p12, $ca] = walletAppearanceTestCertificates();
    Storage::disk('appearance-signing')->put('test.p12', $p12);
    Storage::disk('appearance-signing')->put('test-ca.pem', $ca);
    config([
        'passgenerator.config_disk' => 'appearance-signing',
        'passgenerator.certificate_store_path' => 'test.p12',
        'passgenerator.certificate_store_password' => 'local-test-only',
        'passgenerator.wwdr_certificate_path' => 'test-ca.pem',
        'passgenerator.storage_disk' => 'appearance-passes',
        'passgenerator.storage_path' => 'passes',
        'passgenerator.pass_type_identifier' => 'pass.shop.kawhe.local-test',
        'passgenerator.team_identifier' => 'LOCALTEST',
    ]);
});

test('Apple pass packages contain the selected artwork at every scale through each reward state', function (string $style, string $shape) {
    $account = walletAppearanceAccount($style, $shape);
    $service = app(AppleWalletPassService::class);
    $serial = AppleWalletSerial::fromAccount($account);
    $tokens = [$account->public_token, $account->manual_entry_code, $account->wallet_auth_token];
    $strips = [];

    foreach ([[4, 0], [0, 1], [0, 0]] as [$count, $balance]) {
        $account->update(['stamp_count' => $count, 'reward_balance' => $balance, 'redeem_token' => $balance ? str_repeat('r', 32) : null]);
        $account = $account->fresh();
        $package = walletAppearancePackage($service->generatePass($account));
        $definition = json_decode($package['pass.json'], true, flags: JSON_THROW_ON_ERROR);
        $manifest = json_decode($package['manifest.json'], true, flags: JSON_THROW_ON_ERROR);
        foreach ($manifest as $name => $sha1) {
            expect(sha1($package[$name]))->toBe($sha1);
        }
        expect($package['signature'])->not->toBeEmpty()
            ->and($definition['serialNumber'])->toBe($serial)
            ->and($definition['authenticationToken'])->toBe($tokens[2])
            ->and($definition['barcode']['message'])->toBe($balance ? 'LR:'.str_repeat('r', 32) : 'LA:'.$tokens[0])
            ->and($definition['barcode']['altText'])->toBe('Manual code: '.$tokens[1])
            ->and($definition['foregroundColor'])->toBe('rgb(255,244,207)')
            ->and($definition['storeCard']['headerFields'][0]['value'])->toBe($count.' / 7')
            ->and($definition['storeCard']['auxiliaryFields'][0]['value'])->toBe($balance);
        $artwork = StoreAssets::get(CoffeeCardSample::artwork($account, 'apple'));
        foreach (['strip.png' => 375, 'strip@2x.png' => 750, 'strip@3x.png' => 1125] as $name => $width) {
            expect($package[$name])->toBe(CoffeeCardSample::resizedContents($artwork, $width));
            expect(getimagesizefromstring($package[$name])[0])->toBe($width);
        }
        $strips[] = sha1($package['strip@3x.png']);
    }
    expect(array_unique($strips))->toHaveCount(3);
})->with(['simple', 'illustrated', 'photo'])->with(['circle', 'square', 'rounded']);

test('the Apple download entry point generates a signed package with customized stamps', function () {
    $account = walletAppearanceAccount('simple');
    $this->app->instance(GoogleWalletPassService::class, Mockery::mock(GoogleWalletPassService::class));
    $url = URL::temporarySignedRoute('wallet.apple.download', now()->addMinutes(10), ['public_token' => $account->public_token]);
    $response = $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/vnd.apple.pkpass');
    $package = walletAppearancePackage($response->getContent());
    expect($package['strip@3x.png'])->toBe(CoffeeCardSample::resizedContents(StoreAssets::get(CoffeeCardSample::artwork($account, 'apple')), 1125));
});

test('design edits refresh existing Apple registrations and queue customer Wallet updates without changing balances', function () {
    $account = walletAppearanceAccount('illustrated');
    $program = $account->resolvedProgram();
    $service = app(WalletArtworkService::class);
    $this->freezeTime();
    $service->syncForProgram($program, false);
    $initialVersion = $program->fresh()->wallet_design_version;
    $serial = AppleWalletSerial::fromAccount($account);
    AppleWalletRegistration::create([
        'device_library_identifier' => 'appearance-device', 'pass_type_identifier' => config('passgenerator.pass_type_identifier'),
        'serial_number' => $serial, 'push_token' => str_repeat('a', 64), 'loyalty_account_id' => $account->id, 'active' => true,
    ]);
    $this->travel(5)->seconds();
    $cutoff = now()->timestamp;
    $this->travel(5)->seconds();
    $program->update(['stamp_shape' => 'rounded', 'reward_icon' => 'star', 'stamp_color' => '#FFFFFF']);
    $result = $service->syncForProgram($program->fresh());
    expect($result->changed)->toBeTrue()->and($result->designVersion)->toBe($initialVersion + 1);
    Queue::assertPushed(RefreshProgramWalletsJob::class, fn ($job) => $job->loyaltyProgramId === $program->id);
    (new RefreshProgramWalletsJob($program->id))->handle(app(SupportAuditService::class));
    Queue::assertPushed(UpdateWalletPassJob::class, fn ($job) => $job->loyaltyAccountId === $account->id);
    $this->getJson('/wallet/v1/devices/appearance-device/registrations/'.config('passgenerator.pass_type_identifier').'?passesUpdatedSince='.$cutoff)
        ->assertOk()->assertJsonPath('serialNumbers.0', $serial);
    expect($account->fresh()->stamp_count)->toBe(4)->and($account->fresh()->reward_balance)->toBe(0)
        ->and(AppleWalletSerial::fromAccount($account->fresh()))->toBe($serial);
    $response = $this->get('/wallet/v1/passes/'.config('passgenerator.pass_type_identifier').'/'.$serial, [
        'Authorization' => 'ApplePass '.$account->wallet_auth_token,
        'If-Modified-Since' => gmdate('D, d M Y H:i:s', $cutoff).' GMT',
    ])->assertOk()->assertHeader('Content-Type', 'application/vnd.apple.pkpass');
    $this->get('/wallet/v1/passes/'.config('passgenerator.pass_type_identifier').'/'.$serial, [
        'Authorization' => 'ApplePass '.$account->wallet_auth_token,
        'If-Modified-Since' => $response->headers->get('Last-Modified'),
    ])->assertNotModified();
});

test('card artwork caches include the renderer version and every appearance setting', function () {
    $account = walletAppearanceAccount('simple');
    $options = CardDesign::options($account->resolvedProgram());
    $oldHash = substr(hash('sha256', serialize($options)), 0, 24);
    $current = CoffeeCardSample::artwork($account, 'apple');
    expect($current)->not->toContain('/'.$oldHash.'/');
    $hash = substr(hash('sha256', serialize([CardStyleArtwork::RENDERER_VERSION, $options])), 0, 24);
    expect($current)->toContain('/'.$hash.'/');
});

function walletAppearanceGoogleService(): array
{
    $state = (object) ['loyaltyClass' => null, 'genericClass' => null, 'objects' => []];
    $api = new Google_Service_Walletobjects(new Google_Client);
    foreach (['loyaltyclass' => ['loyaltyClass', Google_Service_Walletobjects_LoyaltyClass::class],
        'genericclass' => ['genericClass', Google_Service_Walletobjects_GenericClass::class]] as $resource => [$field, $class]) {
        $mock = Mockery::mock('Google\\Service\\Walletobjects\\Resource\\'.ucfirst($resource));
        $mock->shouldReceive('get')->andReturnUsing(function () use ($state, $field) {
            if (! $state->$field) {
                throw new Google\Service\Exception('Test class does not exist', 404);
            }

            return $state->$field;
        });
        $mock->shouldReceive('insert')->andReturnUsing(function ($body) use ($state, $field, $class) {
            return $state->$field = new $class(json_decode(json_encode($body), true));
        });
        $mock->shouldReceive('patch')->andReturnUsing(function ($id, $body) use ($state, $field, $class) {
            $old = json_decode(json_encode($state->$field), true) ?? [];
            $patch = json_decode(json_encode($body), true);

            return $state->$field = new $class(array_replace($old, $patch));
        });
        $api->$resource = $mock;
    }
    foreach (['loyaltyobject', 'genericobject'] as $resource) {
        $mock = Mockery::mock('Google\\Service\\Walletobjects\\Resource\\'.ucfirst($resource));
        $mock->shouldReceive('get')->andReturnUsing(function ($id) use ($state) {
            if (! isset($state->objects[$id])) {
                throw new Google\Service\Exception('Test object does not exist', 404);
            }

            return $state->objects[$id];
        });
        $mock->shouldReceive('insert')->andReturnUsing(function ($body) use ($state) {
            return $state->objects[$body->getId()] = clone $body;
        });
        $mock->shouldReceive('patch', 'update')->andReturnUsing(function ($id, $body) use ($state) {
            return $state->objects[$id] = clone $body;
        });
        $api->$resource = $mock;
    }
    $service = (new ReflectionClass(GoogleWalletPassService::class))->newInstanceWithoutConstructor();
    (new ReflectionProperty($service, 'issuerId'))->setValue($service, 'local-test-issuer');
    (new ReflectionProperty($service, 'service'))->setValue($service, $api);
    (new ReflectionProperty($service, 'stampStripRenderer'))->setValue($service, app(\App\Services\Wallet\GoogleWalletStampStripRenderer::class));

    return [$service, $state];
}

test('Google creates and updates actual card payloads with the selected artwork and stable scanning identity', function (string $style, string $type) {
    $account = walletAppearanceAccount($style);
    $program = $account->resolvedProgram();
    [$service, $state] = walletAppearanceGoogleService();
    $method = $type === 'generic' ? 'createOrUpdateGenericObject' : 'createOrUpdateLoyaltyObject';
    $objectId = null;
    $artworkUrls = [];

    foreach ([[4, 0], [0, 1], [0, 0]] as [$count, $balance]) {
        $account->update(['stamp_count' => $count, 'reward_balance' => $balance, 'redeem_token' => $balance ? str_repeat('r', 32) : null]);
        $account = $account->fresh();
        $object = $service->$method($account);
        $objectId ??= $object->getId();
        expect($object->getId())->toBe($objectId)
            ->and($object->getBarcode()->getValue())->toBe($balance ? 'LR:'.str_repeat('r', 32) : 'LA:'.$account->public_token)
            ->and($object->getBarcode()->getAlternateText())->toBe('Manual code: '.$account->manual_entry_code);
        $imageUrl = $object->getHeroImage()->getSourceUri()->getUri();
        $path = CoffeeCardSample::artwork($account, 'google');
        expect($imageUrl)->toEndWith($path)->and(StoreAssets::exists($path))->toBeTrue();
        expect(getimagesizefromstring(StoreAssets::get($path))[0])->toBe(1032);
        $artworkUrls[] = $imageUrl;
        if ($type === 'generic') {
            expect($object->getHexBackgroundColor())->toBe('#263E30')
                ->and($object->getSubheader()->getDefaultValue()->getValue())->toBe($balance > 0 ? $count.' / '.$account->reward_target.' stamps' : 'Reward: '.$account->reward_title);
        } else {
            expect($object->getLoyaltyPoints()->getBalance()->getInt())->toBe($count)
                ->and($object->getSecondaryLoyaltyPoints()->getBalance()->getInt())->toBe($balance)
                ->and($state->loyaltyClass->getHexBackgroundColor())->toBe('#263E30');
        }
    }
    expect(array_unique($artworkUrls))->toHaveCount(3);
    $program->update(['stamp_color' => '#FFFFFF', 'stamp_shape' => 'rounded', 'reward_icon' => 'star']);
    $object = $service->$method($account->fresh());
    expect($object->getId())->toBe($objectId)
        ->and($object->getHeroImage()->getSourceUri()->getUri())->not->toBe(end($artworkUrls));
})->with(['simple', 'illustrated', 'photo'])->with(['loyalty', 'generic']);

test('Google save links are signed locally and reference the customized card object', function (string $type) {
    $account = walletAppearanceAccount('simple');
    [$service, $state] = walletAppearanceGoogleService();
    [$p12] = walletAppearanceTestCertificates();
    openssl_pkcs12_read($p12, $credentials, 'local-test-only');
    $keyPath = Storage::disk('appearance-signing')->path('google-local-test.json');
    file_put_contents($keyPath, json_encode(['client_email' => 'wallet-qa@example.invalid', 'private_key' => $credentials['pkey']]));
    config(['services.google_wallet.service_account_key' => $keyPath, 'services.google_wallet.pass_type' => $type]);
    $url = $service->generateSaveLink($account);
    expect($url)->toStartWith('https://pay.google.com/gp/v/save/');
    $jwt = substr($url, strlen('https://pay.google.com/gp/v/save/'));
    [$header, $body, $signature] = explode('.', $jwt);
    $payload = json_decode(base64_decode(strtr($body, '-_', '+/')), true, flags: JSON_THROW_ON_ERROR);
    $key = $type === 'generic' ? 'genericObjects' : 'loyaltyObjects';
    $id = $payload['payload'][$key][0]['id'];
    expect($payload['iss'])->toBe('wallet-qa@example.invalid')
        ->and($payload['origins'])->toBe(['https://testing.kawhe.shop'])
        ->and(isset($state->objects[$id]))->toBeTrue()
        ->and(openssl_verify($header.'.'.$body, base64_decode(strtr($signature, '-_', '+/')), $credentials['cert'], OPENSSL_ALGO_SHA256))->toBe(1);
})->with(['loyalty', 'generic']);

test('older Google classes with missing text modules can receive a new card design', function () {
    $account = walletAppearanceAccount('simple');
    [$service, $state] = walletAppearanceGoogleService();
    $original = $service->createOrUpdateLoyaltyObject($account);
    $state->loyaltyClass->setTextModulesData([]);
    $state->loyaltyClass->setImageModulesData([]);
    $account->resolvedProgram()->update(['stamp_shape' => 'rounded', 'reward_icon' => 'gift']);
    $updated = $service->createOrUpdateLoyaltyObject($account->fresh());
    expect($updated->getId())->toBe($original->getId())
        ->and($state->loyaltyClass->getTextModulesData())->toHaveCount(2);
});

test('customized Wallet cards complete real stamping and redemption requests with the same card identity', function (string $style) {
    $account = walletAppearanceAccount($style);
    $this->actingAs($account->store->user);
    $apple = app(AppleWalletPassService::class);
    [$google] = walletAppearanceGoogleService();
    $before = json_decode(walletAppearancePackage($apple->generatePass($account))['pass.json'], true);
    $googleBefore = $google->createOrUpdateGenericObject($account);
    $this->postJson('/stamp', ['store_id' => $account->store_id, 'token' => $before['barcode']['message'], 'count' => 3,
        'idempotency_key' => 'appearance-complete-cycle'])->assertOk()->assertJson(['success' => true]);
    $account = $account->fresh();
    expect($account->stamp_count)->toBe(0)->and($account->reward_balance)->toBe(1);
    $readyPackage = walletAppearancePackage($apple->generatePass($account));
    $ready = json_decode($readyPackage['pass.json'], true);
    $googleReady = $google->createOrUpdateGenericObject($account);
    expect($ready['barcode']['message'])->toStartWith('LR:')
        ->and($ready['barcode']['message'])->toBe($googleReady->getBarcode()->getValue())
        ->and($ready['serialNumber'])->toBe($before['serialNumber'])
        ->and($googleReady->getId())->toBe($googleBefore->getId());
    $redemption = ['store_id' => $account->store_id, 'token' => $ready['barcode']['message'], 'idempotency_key' => 'appearance-redeem'];
    $this->postJson('/redeem', $redemption)->assertOk()->assertJson(['success' => true]);
    $this->postJson('/redeem', $redemption)->assertOk()->assertJson(['status' => 'duplicate', 'success' => false]);
    $account = $account->fresh();
    $afterPackage = walletAppearancePackage($apple->generatePass($account));
    $after = json_decode($afterPackage['pass.json'], true);
    $googleAfter = $google->createOrUpdateGenericObject($account);
    expect($account->reward_balance)->toBe(0)->and($account->stamp_count)->toBe(0)
        ->and($after['serialNumber'])->toBe($before['serialNumber'])
        ->and($after['barcode']['altText'])->toBe($before['barcode']['altText'])
        ->and($after['barcode']['message'])->toBe($before['barcode']['message'])
        ->and($googleAfter->getId())->toBe($googleBefore->getId())
        ->and($googleAfter->getBarcode()->getValue())->toBe($after['barcode']['message'])
        ->and($afterPackage['strip@3x.png'])->not->toBe($readyPackage['strip@3x.png'])
        ->and($account->resolvedProgram()->stamp_color)->toBe('#FFD166')
        ->and($account->resolvedProgram()->reward_icon)->toBe('custom');
})->with(['simple', 'illustrated', 'photo']);
