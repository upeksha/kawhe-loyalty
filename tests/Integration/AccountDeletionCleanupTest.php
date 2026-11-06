<?php

use App\Models\AccountFileCleanup;
use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;
use App\Services\Billing\AccountDeletionService;
use App\Services\Billing\AccountFileDeletion;
use App\Support\CardDesign;
use App\Support\StoreAssets;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

beforeEach(function () {
    if (! app()->environment('testing') || ! in_array(config('database.connections.'.config('database.default').'.database'), [':memory:', 'kawhe_deletion_qa'], true)) {
        throw new RuntimeException('Deletion tests require an isolated disposable database.');
    }
    $this->artisan('migrate:fresh')->assertSuccessful();
    config(['filesystems.assets_disk' => 'public', 'passgenerator.storage_disk' => 'local', 'passgenerator.storage_path' => 'passgenerator/passes']);
    Storage::fake('public');
    Storage::fake('local');
});

function deletionFixture(): array
{
    $owner = User::factory()->create();
    $store = Store::factory()->for($owner)->create([
        'logo_path' => 'logos/legacy-logo.png', 'pass_logo_path' => 'pass-logos/legacy.png',
        'pass_hero_image_path' => 'pass-heroes/legacy.png', 'card_photo_path' => 'card-designs/photo.jpg',
        'stamp_icon_path' => 'card-designs/icon.png', 'card_design' => 'photo',
    ]);
    $account = LoyaltyAccount::factory()->create(['store_id' => $store->id, 'customer_id' => Customer::factory()]);
    $program = $account->loyaltyProgram;
    $program->update($store->only(['logo_path', 'pass_logo_path', 'pass_hero_image_path', 'card_photo_path', 'stamp_icon_path', 'card_design']));
    foreach (['logos/legacy-logo.png', 'pass-logos/legacy.png', 'pass-heroes/legacy.png', 'card-designs/photo.jpg', 'card-designs/icon.png'] as $path) {
        Storage::disk('public')->put($path, 'fixture-'.$path);
    }
    $paths = [
        "logos/accounts/{$owner->id}/replaced-logo.png",
        "card-designs/accounts/{$owner->id}/old-photo.jpg",
        "wallet/programs/{$program->id}/apple/old-version.png",
        "wallet/google/stamp-strips/store_{$store->id}_account_{$account->id}_old.png",
        "wallet/google/stamp-strips/program_{$program->id}_account_{$account->id}_new.png",
        "wallet/card-styles-v2/accounts/{$owner->id}/old-hash/apple-5-1.png",
    ];
    foreach ($paths as $path) {
        Storage::disk('public')->put($path, 'generated');
    }
    $hash = substr(hash('sha256', serialize(CardDesign::options($program))), 0, 24);
    $paths[] = "wallet/card-styles-v1/{$hash}/apple-5-1.png";
    Storage::disk('public')->put(end($paths), 'legacy generated');
    foreach (["kawhe-{$account->id}", "kawhe-{$store->id}-{$account->customer_id}"] as $serial) {
        Storage::disk('local')->put("passgenerator/passes/{$serial}.pkpass", 'pass');
        Storage::disk('local')->put("passgenerator/passes/{$serial}/pass.json", 'private pass');
    }
    Storage::disk('local')->put('passgenerator/certs/certificate.p12', 'shared signing key');

    return [$owner, $store, $program, $account, $paths];
}

test('permanent deletion cleans archived stores current and historical owned images and passes', function () {
    [$owner, $store, $program, $account, $paths] = deletionFixture();
    $customerId = $account->customer_id;
    $program->delete();
    $store->delete();
    $owner->createToken('device');
    DB::table('password_reset_tokens')->insert(['email' => $owner->email, 'token' => 'test', 'created_at' => now()]);
    DB::table('support_audit_logs')->insert(['store_id' => $store->id, 'actor_user_id' => $owner->id, 'event_type' => 'test', 'metadata' => '{"private":"test"}']);
    DB::table('apple_wallet_registrations')->insert(['device_library_identifier' => 'test-device', 'push_token' => 'test', 'pass_type_identifier' => 'test', 'serial_number' => "kawhe-{$account->id}"]);
    app(AccountDeletionService::class)->delete($owner);
    foreach (['stores' => $store->id, 'loyalty_programs' => $program->id, 'loyalty_accounts' => $account->id, 'customers' => $customerId] as $table => $id) {
        $this->assertDatabaseMissing($table, ['id' => $id]);
    }
    foreach (['personal_access_tokens', 'password_reset_tokens', 'support_audit_logs', 'apple_wallet_registrations'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
    Storage::disk('public')->assertExists('logos/legacy-logo.png');
    $cleanup = AccountFileCleanup::sole();
    expect($cleanup->available_at->isFuture())->toBeTrue();
    $cleanup->update(['available_at' => now()]);
    $this->artisan('accounts:cleanup-files')->assertSuccessful();
    Storage::disk('public')->assertMissing([...$paths, 'logos/legacy-logo.png', 'pass-logos/legacy.png', 'pass-heroes/legacy.png', 'card-designs/photo.jpg', 'card-designs/icon.png']);
    expect(Storage::disk('local')->allFiles('passgenerator/passes'))->toBeEmpty();
    Storage::disk('local')->assertExists('passgenerator/certs/certificate.p12');
    expect($cleanup->fresh()->completed_at)->not->toBeNull();
    expect($cleanup->fresh()->payload)->toBe([]);
    expect(app(AccountFileDeletion::class)->process($cleanup->fresh()))->toBeTrue();
});

test('other merchant archived cards keep shared customer images balances and staff history', function () {
    [$owner, $store, $program, $account] = deletionFixture();
    $otherOwner = User::factory()->create();
    $otherStore = Store::factory()->for($otherOwner)->create(['logo_path' => $store->logo_path]);
    $otherAccount = LoyaltyAccount::factory()->create(['store_id' => $otherStore->id, 'customer_id' => $account->customer_id, 'stamp_count' => 2, 'reward_balance' => 1]);
    $otherProgram = $otherAccount->loyaltyProgram;
    $otherProgram->update($program->only(['card_design', 'card_pattern', 'stamp_icon', 'stamp_icon_path', 'card_photo_path', 'brand_color', 'background_color']));
    $hash = substr(hash('sha256', serialize(CardDesign::options($program))), 0, 24);
    $sharedLegacy = "wallet/card-styles-v1/{$hash}/apple-5-1.png";
    $otherProgram->delete();
    $otherStore->delete();
    $eventId = DB::table('stamp_events')->insertGetId(['store_id' => $otherStore->id, 'loyalty_account_id' => $otherAccount->id, 'user_id' => $owner->id, 'type' => 'stamp', 'ip_address' => '192.0.2.1', 'user_agent' => 'QA']);
    $otherPath = "wallet/programs/{$otherProgram->id}/apple/keep.png";
    Storage::disk('public')->put($otherPath, 'keep');
    app(AccountDeletionService::class)->delete($owner);
    expect(app(AccountFileDeletion::class)->process(AccountFileCleanup::sole()))->toBeTrue();
    Storage::disk('public')->assertExists([$store->logo_path, $otherPath, $sharedLegacy, 'card-designs/photo.jpg', 'card-designs/icon.png']);
    $this->assertDatabaseHas('customers', ['id' => $account->customer_id]);
    $this->assertDatabaseHas('loyalty_accounts', ['id' => $otherAccount->id, 'stamp_count' => 2, 'reward_balance' => 1]);
    $this->assertDatabaseHas('stamp_events', ['id' => $eventId, 'user_id' => null, 'ip_address' => null, 'user_agent' => null]);
    $this->assertSoftDeleted('stores', ['id' => $otherStore->id]);
});

test('archive alone remains reversible and never schedules cleanup', function () {
    [$owner, $store, $program, $account, $paths] = deletionFixture();
    $program->delete();
    $store->delete();
    $this->assertDatabaseCount('account_file_cleanups', 0);
    Storage::disk('public')->assertExists($paths);
    $store->restore();
    $program->restore();
    expect($account->fresh())->not->toBeNull();
});

test('rollback restores data and cleanup intent without touching images', function () {
    [$owner, $store, $program, $account, $paths] = deletionFixture();
    DB::beginTransaction();
    app(AccountDeletionService::class)->delete($owner);
    expect(fn () => app(AccountFileDeletion::class)->process(AccountFileCleanup::sole()))->toThrow(RuntimeException::class, 'after commit');
    DB::rollBack();
    $this->assertDatabaseHas('users', ['id' => $owner->id]);
    $this->assertDatabaseHas('loyalty_accounts', ['id' => $account->id]);
    $this->assertDatabaseCount('account_file_cleanups', 0);
    Storage::disk('public')->assertExists($paths);
});

test('storage failure stays durable and a retry removes files', function () {
    [$owner] = deletionFixture();
    app(AccountDeletionService::class)->delete($owner);
    $cleanup = AccountFileCleanup::sole();
    $realDisk = Storage::disk('public');
    $failing = Mockery::mock($realDisk);
    $failing->shouldReceive('delete')->andReturn(false);
    Storage::set('public', $failing);
    expect(app(AccountFileDeletion::class)->process($cleanup))->toBeFalse();
    expect($cleanup->fresh()->completed_at)->toBeNull();
    expect($cleanup->fresh()->payload['expanded'])->toBeTrue();
    expect($cleanup->fresh()->attempts)->toBe(1);
    Storage::set('public', $realDisk);
    expect(app(AccountFileDeletion::class)->process($cleanup->fresh()))->toBeTrue();
    $realDisk->assertMissing('logos/legacy-logo.png');
});

test('owner namespace retains replaced uploads for deletion', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    $first = StoreAssets::storeUploaded(UploadedFile::fake()->image('logo.png'), 'logos');
    $second = StoreAssets::storeUploaded(UploadedFile::fake()->image('new.png'), 'logos');
    expect($first)->toStartWith("logos/accounts/{$owner->id}/");
    Store::factory()->for($owner)->create(['logo_path' => $second]);
    app(AccountDeletionService::class)->delete($owner);
    expect(app(AccountFileDeletion::class)->process(AccountFileCleanup::sole()))->toBeTrue();
    Storage::disk('public')->assertMissing([$first, $second]);
});

test('unsafe artwork paths cannot delete backups', function () {
    [$owner, $store] = deletionFixture();
    $store->update(['logo_path' => '../backups/important.sql']);
    Storage::disk('public')->put('backups/important.sql', 'keep');
    app(AccountDeletionService::class)->delete($owner);
    expect(app(AccountFileDeletion::class)->process(AccountFileCleanup::sole()))->toBeFalse();
    Storage::disk('public')->assertExists('backups/important.sql');
});

test('sessions memberships invitations and recovery links are removed with owned data', function () {
    [$owner, $store, $program, $account] = deletionFixture();
    config(['session.driver' => 'database']);
    DB::table('sessions')->insert(['id' => 'qa-session', 'user_id' => $owner->id, 'payload' => '', 'last_activity' => time()]);
    $staff = User::factory()->create();
    DB::table('store_staff')->insert(['store_id' => $store->id, 'user_id' => $staff->id]);
    DB::table('store_staff_invitations')->insert(['store_id' => $store->id, 'invited_by' => $owner->id, 'email' => 'staff@example.invalid', 'token_hash' => str_repeat('a', 64), 'can_stamp' => true, 'can_redeem' => false, 'expires_at' => now()->addDay()]);
    DB::table('customer_card_recoveries')->insert(['customer_id' => $account->customer_id, 'loyalty_program_id' => $program->id, 'token_hash' => str_repeat('b', 64), 'email_hash' => str_repeat('c', 64), 'expires_at' => now()->addDay(), 'created_at' => now()]);
    DB::table('stamp_events')->insert(['store_id' => $store->id, 'loyalty_account_id' => $account->id, 'user_id' => $owner->id]);
    DB::table('points_transactions')->insert(['store_id' => $store->id, 'loyalty_account_id' => $account->id, 'user_id' => $owner->id, 'type' => 'earn', 'points' => 1, 'idempotency_key' => 'qa-stamp']);
    app(AccountDeletionService::class)->delete($owner);
    foreach (['sessions', 'store_staff', 'store_staff_invitations', 'customer_card_recoveries', 'stamp_events', 'points_transactions'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
    expect($staff->fresh())->not->toBeNull();
});

test('pending enrollment at another cafe keeps the shared customer identity', function () {
    [$owner, $store, $program, $account] = deletionFixture();
    $otherStore = Store::factory()->create();
    $otherProgram = $otherStore->resolvedDefaultProgram();
    DB::table('customer_card_recoveries')->insert(['customer_id' => $account->customer_id, 'loyalty_program_id' => $otherProgram->id, 'token_hash' => str_repeat('b', 64), 'email_hash' => str_repeat('c', 64), 'allow_enrollment' => true, 'expires_at' => now()->addDay(), 'created_at' => now()]);
    app(AccountDeletionService::class)->delete($owner);
    $this->assertDatabaseHas('customers', ['id' => $account->customer_id]);
    $this->assertDatabaseCount('customer_card_recoveries', 1);
});

test('storage listing outage cannot lose the pending cleanup request', function () {
    [$owner] = deletionFixture();
    app(AccountDeletionService::class)->delete($owner);
    $realDisk = Storage::disk('public');
    $failing = Mockery::mock($realDisk);
    $failing->shouldReceive('allFiles')->andThrow(new RuntimeException('Simulated storage outage'));
    Storage::set('public', $failing);
    expect(app(AccountFileDeletion::class)->process(AccountFileCleanup::sole()))->toBeFalse();
    expect(AccountFileCleanup::sole()->payload)->not->toHaveKey('expanded');
    $realDisk->assertExists('logos/legacy-logo.png');
    Storage::set('public', $realDisk);
    expect(app(AccountFileDeletion::class)->process(AccountFileCleanup::sole()))->toBeTrue();
});

test('cleanup refuses a request while its account still exists and health detects overdue work', function () {
    [$owner] = deletionFixture();
    $cleanup = AccountFileCleanup::create(['deleted_user_id' => $owner->id, 'payload' => [], 'available_at' => now(), 'created_at' => now()->subHours(2)]);
    expect(app(AccountFileDeletion::class)->process($cleanup))->toBeFalse();
    Storage::disk('public')->assertExists('logos/legacy-logo.png');
    $probe = new class extends \App\Console\Commands\HealthCheck
    {
        public array $failures = [];

        public function inspect(): void
        {
            $this->checkAccountFileCleanups();
        }

        protected function failResult(string $message): void
        {
            $this->failures[] = $message;
        }
    };
    $probe->inspect();
    expect(implode(' ', $probe->failures))->toContain('cleanup overdue');
});

test('an expired recovery alone does not retain an otherwise orphaned customer', function () {
    [$owner, $store, $program, $account] = deletionFixture();
    $otherProgram = Store::factory()->create()->resolvedDefaultProgram();
    DB::table('customer_card_recoveries')->insert(['customer_id' => $account->customer_id, 'loyalty_program_id' => $otherProgram->id, 'token_hash' => str_repeat('b', 64), 'email_hash' => str_repeat('c', 64), 'allow_enrollment' => true, 'expires_at' => now()->subDay(), 'created_at' => now()->subDays(2)]);
    app(AccountDeletionService::class)->delete($owner);
    $this->assertDatabaseMissing('customers', ['id' => $account->customer_id]);
    $this->assertDatabaseCount('customer_card_recoveries', 0);
    expect($otherProgram->fresh())->not->toBeNull();
});
