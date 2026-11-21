<?php

use App\Models\LoyaltyAccount;
use App\Models\StampEvent;
use App\Models\Store;
use App\Models\SupportAuditLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

function operationsFixture(string $name = 'TEST Operations Cafe'): array
{
    $store = Store::factory()->create(['name' => $name]);
    $account = LoyaltyAccount::factory()->create(['store_id' => $store->id, 'loyalty_program_id' => $store->resolvedDefaultProgram()->id]);

    return [$store, $account];
}

function operationsEvent($store, $account, string $type, int $quantity, $time = null): void
{
    StampEvent::forceCreate(['store_id' => $store->id, 'loyalty_account_id' => $account->id, 'user_id' => $store->user_id,
        'type' => $type, 'count' => $quantity, 'idempotency_key' => Illuminate\Support\Str::uuid()->toString(),
        'created_at' => $time ?? now(), 'updated_at' => $time ?? now()]);
}

afterEach(fn () => CarbonImmutable::setTestNow());

test('operations routes require a verified super admin and expose only GET reporting', function () {
    [$store] = operationsFixture();
    $paths = ['/admin/dashboard', '/admin/stores', '/admin/stores/'.$store->id, '/admin/transactions', '/admin/issues'];
    foreach ($paths as $path) {
        $this->get($path)->assertRedirect('/login');
    }
    $this->actingAs(User::factory()->create());
    foreach ($paths as $path) {
        $this->get($path)->assertForbidden();
    }
    $admin = User::factory()->create(['is_super_admin' => true, 'email_verified_at' => null]);
    $this->actingAs($admin)->get('/admin/dashboard')->assertRedirect(route('verification.notice'));
    $admin->forceFill(['email_verified_at' => now()])->save();
    $this->actingAs($admin);
    foreach ($paths as $path) {
        $this->get($path)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->post($path)->assertStatus(405);
    }
});

test('overview counts quantities and undos while separating people memberships and classified test stores', function () {
    [$store, $account] = operationsFixture();
    [$testStore, $testAccount] = operationsFixture('A real-looking name explicitly classified as test');
    config(['admin_operations.test_store_ids' => [$testStore->id]]);
    $programme = $store->loyaltyPrograms()->create(['name' => 'Second programme', 'reward_target' => 7, 'reward_title' => 'Coffee']);
    LoyaltyAccount::factory()->create(['store_id' => $store->id, 'loyalty_program_id' => $programme->id, 'customer_id' => $account->customer_id]);
    operationsEvent($store, $account, 'stamp', 4);
    operationsEvent($store, $account, 'stamp_undo', 1);
    operationsEvent($store, $account, 'redeem', 2);
    operationsEvent($testStore, $testAccount, 'stamp', 99);
    $this->actingAs(User::factory()->create(['is_super_admin' => true]));
    $response = $this->get('/admin/dashboard?scope=customers')->assertOk();
    $stats = $response->viewData('stats');
    expect($stats['stamps'])->toBe(4)->and($stats['undos'])->toBe(1)->and($stats['net'])->toBe(3)->and($stats['redeems'])->toBe(2)
        ->and($stats['customers'])->toBe(1)->and($stats['cards'])->toBe(2)->and($stats['stores'])->toBe(1);
    $this->get('/admin/dashboard?scope=all')->assertOk()->assertViewHas('stats', fn ($s) => $s['stamps'] === 103);
    $this->get('/admin/stores?scope=customers')->assertDontSee($testStore->name);
    $this->get('/admin/stores/'.$testStore->id.'?scope=customers')->assertNotFound();
    $this->get('/admin/stores/'.$testStore->id.'?scope=test')->assertOk();
});

test('NZ day boundaries include the correct UTC events and validate reporting ranges', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-21 00:30:00', 'Pacific/Auckland'));
    [$store,$account] = operationsFixture();
    operationsEvent($store, $account, 'stamp', 100, '2026-11-20 10:59:59');
    operationsEvent($store, $account, 'stamp', 3, '2026-11-20 11:00:00');
    $this->actingAs(User::factory()->create(['is_super_admin' => true]));
    $response = $this->get('/admin/dashboard?range=today')->assertOk();
    expect($response->viewData('stats')['stamps'])->toBe(3)
        ->and($response->viewData('stats')['trend'])->toHaveCount(1)
        ->and($response->viewData('stats')['trend'][0]['stamp'])->toBe(3);
    $this->getJson('/admin/dashboard?from=2026-01-01&to=2026-11-21')->assertUnprocessable();
    $this->getJson('/admin/dashboard?from=2026-11-21&to=2026-11-20')->assertUnprocessable();
    $this->getJson('/admin/dashboard?from=2026-11-21&to=2026-11-22')->assertUnprocessable();
});

test('issues omit raw error data credentials tokens and successful or queued events', function () {
    [$store,$account] = operationsFixture();
    $secret = 'DO_NOT_DISPLAY_PRIVATE_BEARER_TOKEN';
    foreach (['failed', 'success', 'queued'] as $status) {
        SupportAuditLog::create(['store_id' => $store->id, 'event_type' => 'wallet_sync', 'status' => $status, 'message' => $secret, 'metadata' => ['token' => $account->public_token]]);
    }
    DB::table('failed_jobs')->insert(['uuid' => (string) Illuminate\Support\Str::uuid(), 'connection' => 'database', 'queue' => 'emails', 'payload' => $secret, 'exception' => $secret, 'failed_at' => now()]);
    $this->actingAs(User::factory()->create(['is_super_admin' => true]));
    $response = $this->get('/admin/issues')->assertOk()->assertSee('Wallet update needs review')->assertDontSee($secret)->assertDontSee($account->public_token);
    expect($response->viewData('issues')->total())->toBe(1)->and($response->viewData('queue')['failed'])->toBe(1);
    $response->assertSee('not connected to this dashboard yet')->assertSee('emails');
});

test('store search transaction filters and pagination remain scoped and preserve business records', function () {
    [$store,$account] = operationsFixture('TEST Harbour');
    [$other,$otherAccount] = operationsFixture('TEST Garden');
    operationsEvent($store, $account, 'stamp', 3);
    operationsEvent($other, $otherAccount, 'redeem', 1);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($admin);
    $before = [];
    foreach (['stores', 'loyalty_programs', 'users', 'customers', 'loyalty_accounts', 'stamp_events', 'points_transactions', 'support_audit_logs'] as $table) {
        $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
    }
    $this->get('/admin/stores?q=Harbour')->assertOk()->assertSee('TEST Harbour')->assertDontSee('TEST Garden');
    $response = $this->get('/admin/transactions?type=stamp&store_id='.$store->id)->assertOk();
    expect($response->viewData('events')->total())->toBe(1)->and($response->viewData('events')->first()->store_id)->toBe($store->id);
    $this->get('/admin/stores/'.$store->id)->assertOk();
    $this->get('/admin/dashboard')->assertOk();
    $this->get('/admin/issues')->assertOk();
    foreach ($before as $table => $rows) {
        expect(DB::table($table)->orderBy('id')->get()->toJson())->toBe($rows);
    }
    foreach (range(1, 31) as $i) {
        operationsEvent($store, $account, 'stamp', 1);
    }
    $this->get('/admin/transactions?store_id='.$store->id.'&type=stamp')->assertViewHas('events', fn ($p) => $p->count() === 30 && $p->total() === 32);
    $this->get('/admin/transactions?store_id='.$store->id.'&type=stamp&page=2')->assertViewHas('events', fn ($p) => $p->count() === 2);
});

test('summary uses its cache and archived store history remains accessible without changing records', function () {
    [$store, $account] = operationsFixture('TEST Archive History');
    operationsEvent($store, $account, 'stamp', 5);
    $store->forceFill(['deleted_at' => now()])->save();
    $this->actingAs(User::factory()->create(['is_super_admin' => true]));
    $this->get('/admin/stores?status=active')->assertDontSee($store->name);
    $this->get('/admin/stores?status=archived')->assertSee($store->name);
    $this->get('/admin/stores/'.$store->id)->assertOk()->assertSee('Archived');
    $report = app(App\Services\Admin\OperationsReport::class);
    $filters = $report->filters(Illuminate\Http\Request::create('/admin/dashboard'));
    Cache::flush();
    $first = $report->summary($filters);
    DB::enableQueryLog();
    DB::flushQueryLog();
    expect($report->summary($filters))->toBe($first)->and(DB::getQueryLog())->toHaveCount(0);
    DB::disableQueryLog();
    expect($first['stamps'])->toBe(5)->and($first['stores'])->toBe(0);
});
