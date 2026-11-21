<?php

use App\Models\Store;
use App\Models\User;
use App\Services\Admin\BillingReport;
use App\Services\Admin\OperationsReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

function adminBillingOwner(string $status = 'active', array $attributes = []): User
{
    $user = User::factory()->create(['stripe_id' => 'cus_'.Illuminate\Support\Str::random(10)]);
    Store::factory()->create(['user_id' => $user->id]);
    $user->subscriptions()->create(array_merge(['type' => 'default', 'stripe_id' => 'sub_'.$user->id, 'stripe_status' => $status, 'stripe_price' => 'price_pro', 'quantity' => 1], $attributes));

    return $user;
}

function adminBillingFilters(): array
{
    return app(OperationsReport::class)->filters(Request::create('/admin/revenue', 'GET', ['scope' => 'all']));
}

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
    config(['cashier.price_id' => 'price_pro', 'admin_operations.test_store_ids' => []]);
});

test('billing reports require verified super admin access and never expose a write route', function () {
    $this->get('/admin/revenue')->assertRedirect('/login');
    $this->actingAs(User::factory()->create())->get('/admin/revenue')->assertForbidden();
    $admin = User::factory()->unverified()->create(['is_super_admin' => true]);
    $this->actingAs($admin)->get('/admin/revenue')->assertRedirect(route('verification.notice'));
    $admin->forceFill(['email_verified_at' => now()])->save();
    $this->get('/admin/revenue')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    $this->post('/admin/revenue')->assertStatus(405);
    Http::assertNothingSent();
});

test('plan totals count owners once and match trial expired price and scope entitlements', function () {
    $active = adminBillingOwner();
    Store::factory()->create(['user_id' => $active->id]);
    adminBillingOwner('trialing');
    adminBillingOwner('past_due');
    adminBillingOwner('active', ['ends_at' => now()->subDay()]);
    adminBillingOwner('active', ['stripe_price' => 'price_unrelated']);
    adminBillingOwner('active', ['ends_at' => now()->addDay()]);
    $free = User::factory()->create();
    $testStore = Store::factory()->create(['user_id' => $free->id]);
    User::factory()->create(['is_staff_account' => true]);
    config(['admin_operations.test_store_ids' => [$testStore->id]]);
    $report = app(BillingReport::class);
    $counts = $report->plans(adminBillingFilters());
    expect($counts)->toMatchArray(['accounts' => 7, 'pro' => 3, 'free' => 4, 'active' => 2, 'trialing' => 1, 'past_due' => 1, 'cancelling' => 1]);
    $f = adminBillingFilters();
    $f['scope'] = 'test';
    expect($report->plans($f))->toMatchArray(['accounts' => 1, 'pro' => 0, 'free' => 1]);
    $f['scope'] = 'customers';
    expect($report->plans($f)['accounts'])->toBe(6);
    $f['store_id'] = $testStore->id;
    expect($report->plans($f)['accounts'])->toBe(0);
    $f['scope'] = 'test';
    expect($report->plans($f)['accounts'])->toBe(1);
    Http::assertNothingSent();
});

test('local revenue reporting refuses live credentials and leaves billing records untouched', function () {
    adminBillingOwner();
    config(['admin_operations.stripe_reporting' => true, 'cashier.secret' => 'sk_live_never_send_this']);
    $before = DB::table('subscriptions')->get()->toJson();
    $this->actingAs(User::factory()->create(['is_super_admin' => true]));
    $this->get('/admin/revenue?scope=all')->assertOk()->assertSee('Amounts are unavailable, not zero.')->assertDontSee('sk_live_never_send_this');
    expect(DB::table('subscriptions')->get()->toJson())->toBe($before);
    Http::assertNothingSent();
});

test('read only sandbox report uses payment dates scoped subscription IDs and separate currencies', function () {
    $owner = adminBillingOwner();
    adminBillingOwner('trialing');
    $annual = adminBillingOwner('active', ['stripe_price' => 'price_annual', 'quantity' => 2]);
    config(['billing.legacy_pro_price_ids' => ['price_annual'], 'admin_operations.stripe_reporting' => true, 'cashier.secret' => 'sk_test_reporting']);
    $f = adminBillingFilters();
    $invoice = fn ($sub, $currency, $amount, $paidAt, $live = false) => ['id' => 'in_'.Illuminate\Support\Str::random(8), 'parent' => ['subscription_details' => ['subscription' => $sub]], 'currency' => $currency, 'amount_paid' => $amount, 'livemode' => $live, 'status_transitions' => ['paid_at' => $paidAt]];
    $invoices = [
        $invoice('sub_'.$owner->id, 'nzd', 2900, $f['start']->timestamp),
        $invoice('sub_'.$annual->id, 'jpy', 1500, $f['start']->addHour()->timestamp),
        $invoice('sub_other_product', 'nzd', 99999, $f['start']->timestamp),
        $invoice('sub_'.$owner->id, 'nzd', 99999, $f['start']->subSecond()->timestamp),
        $invoice('sub_'.$owner->id, 'nzd', 99999, $f['end']->timestamp),
        $invoice('sub_'.$owner->id, 'nzd', 99999, $f['start']->timestamp, true),
    ];
    Http::fake([
        'api.stripe.com/v1/invoices*' => Http::response(['data' => $invoices, 'has_more' => false]),
        'api.stripe.com/v1/prices/price_pro' => Http::response(['currency' => 'nzd', 'livemode' => false, 'unit_amount' => 2900, 'recurring' => ['interval' => 'month', 'interval_count' => 1]]),
        'api.stripe.com/v1/prices/price_annual' => Http::response(['currency' => 'nzd', 'livemode' => false, 'unit_amount' => 24000, 'recurring' => ['interval' => 'year', 'interval_count' => 1]]),
    ]);
    $report = app(BillingReport::class)->revenue($f);
    expect($report)->toMatchArray(['state' => 'ready', 'mode' => 'test', 'paid' => ['nzd' => 2900, 'jpy' => 1500], 'invoices' => 2, 'unknown' => 0]);
    expect($report['mrr']['nzd'])->toEqual(6900);
    Http::assertSentCount(3);
    Http::assertSent(fn ($r) => str_contains($r->url(), '/invoices') && ! isset($r['created']['gte']));
    app(BillingReport::class)->revenue($f);
    Http::assertSentCount(3);
    $this->actingAs(User::factory()->create(['is_super_admin' => true]));
    $this->get('/admin/revenue?scope=all')->assertOk()->assertSee('Sandbox amounts only')->assertSee('Paid invoice total')->assertSee('before refunds')->assertDontSee('sk_test_reporting');
    Http::assertSent(fn ($r) => $r->method() === 'GET');
    Http::assertNotSent(fn ($r) => $r->method() !== 'GET');
});

test('provider failure shows unavailable amounts without raw errors', function () {
    adminBillingOwner();
    config(['admin_operations.stripe_reporting' => true, 'cashier.secret' => 'sk_test_reporting']);
    Http::fake(['api.stripe.com/*' => Http::response(['error' => ['message' => 'private provider secret']], 403)]);
    expect(app(BillingReport::class)->revenue(adminBillingFilters()))->toMatchArray(['state' => 'unavailable']);
});

test('scan limits show unavailable amounts rather than partial totals', function () {
    adminBillingOwner();
    config(['admin_operations.stripe_reporting' => true, 'cashier.secret' => 'sk_test_reporting']);
    Http::fake(['api.stripe.com/*' => Http::response(['data' => [['id' => 'in_page']], 'has_more' => true])]);
    $result = app(BillingReport::class)->revenue(adminBillingFilters());
    expect($result['state'])->toBe('too_many')->and($result)->not->toHaveKey('paid');
});

test('reporting key is isolated from checkout credentials and missing fixture prices are explicit', function () {
    adminBillingOwner();
    config(['admin_operations.stripe_reporting' => true, 'admin_operations.stripe_reporting_key' => 'rk_test_readonly', 'cashier.secret' => null]);
    Http::fake([
        'api.stripe.com/v1/invoices*' => Http::response(['data' => [], 'has_more' => false]),
        'api.stripe.com/v1/prices/*' => Http::response(['error' => ['message' => 'No such price']], 404),
    ]);
    expect(app(BillingReport::class)->revenue(adminBillingFilters()))->toMatchArray(['state' => 'ready', 'unknown' => 1, 'paid' => [], 'mrr' => []]);
    expect(config('cashier.secret'))->toBeNull();
    Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer rk_test_readonly') && $r->method() === 'GET');
});
