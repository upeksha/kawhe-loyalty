<?php

use App\Models\User;
use App\Services\Billing\UsageService;
use Stripe\ApiRequestor;
use Stripe\HttpClient\CurlClient;
use Tests\Support\BillingStripeHttpFake;

beforeEach(function () {
    config(['cashier.secret' => 'sk_test_fake', 'cashier.key' => 'pk_test_fake', 'cashier.price_id' => 'price_pro', 'cashier.webhook.secret' => 'whsec_testing']);
    $this->http = new BillingStripeHttpFake;
    ApiRequestor::setHttpClient($this->http);
    $this->owner = User::factory()->create(['stripe_id' => 'cus_lifecycle']);
    $this->remote = [
        'id' => 'sub_lifecycle', 'object' => 'subscription', 'customer' => 'cus_lifecycle', 'status' => 'active', 'created' => time() - 100,
        'trial_end' => null, 'cancel_at' => null, 'cancel_at_period_end' => false, 'ended_at' => null,
        'items' => ['object' => 'list', 'data' => [['id' => 'si_lifecycle', 'object' => 'subscription_item', 'quantity' => 1, 'current_period_end' => time() + 86400, 'price' => ['id' => 'price_pro', 'object' => 'price', 'product' => 'prod_pro']]]],
    ];
    $this->session = ['id' => 'cs_lifecycle', 'object' => 'checkout.session', 'customer' => 'cus_lifecycle', 'client_reference_id' => (string) $this->owner->id, 'mode' => 'subscription', 'status' => 'complete', 'payment_status' => 'paid', 'subscription' => 'sub_lifecycle'];
    $this->http->respond = function ($method, $path) {
        if ($path === '/v1/checkout/sessions/cs_lifecycle') {
            return [json_encode($this->session), 200, []];
        }
        if ($path === '/v1/subscriptions/sub_lifecycle') {
            return [json_encode($this->remote), 200, []];
        }
        if ($path === '/v1/subscriptions') {
            return $this->http->collection([$this->remote], $path);
        }

        return null;
    };
});
afterEach(fn () => ApiRequestor::setHttpClient(new CurlClient));

function syncBillingLifecycle($test)
{
    return $test->actingAs($test->owner)->postJson(route('billing.sync'));
}
function billingWebhook($test, array $payload, string $secret = 'whsec_testing')
{
    $body = json_encode($payload);
    $t = time();
    $signature = 't='.$t.',v1='.hash_hmac('sha256', $t.'.'.$body, $secret);

    return $test->call('POST', '/stripe/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature], $body);
}
function lifecycleEvent($test, string $type = 'customer.subscription.updated')
{
    return ['id' => 'evt_lifecycle', 'object' => 'event', 'livemode' => false, 'type' => $type, 'data' => ['object' => $test->remote]];
}

test('paid checkout activates only its authenticated owner', function () {
    $this->actingAs($this->owner)->get(route('billing.success', ['session_id' => 'cs_lifecycle']))->assertRedirect(route('dashboard'));
    expect(app(UsageService::class)->planFor($this->owner->fresh()))->toBe('pro');
});

test('success and sync reject another customers checkout even without a reference', function () {
    $this->session['customer'] = 'cus_foreign';
    $this->session['client_reference_id'] = null;
    $this->actingAs($this->owner)->get(route('billing.success', ['session_id' => 'cs_lifecycle']))->assertForbidden();
    $this->postJson(route('billing.sync'), ['session_id' => 'cs_lifecycle'])->assertForbidden();
    $this->assertDatabaseCount('subscriptions', 0);
});

test('checkout cannot attach a Stripe customer to an unrelated unlinked account', function () {
    $this->owner->forceFill(['stripe_id' => null])->save();
    $this->actingAs($this->owner)->postJson(route('billing.sync'), ['session_id' => 'cs_lifecycle'])->assertForbidden();
    expect($this->owner->fresh()->stripe_id)->toBeNull();
});

test('incomplete and unpaid checkout returns do not activate pro', function ($status, $payment) {
    $this->session['status'] = $status;
    $this->session['payment_status'] = $payment;
    $this->actingAs($this->owner)->get(route('billing.success', ['session_id' => 'cs_lifecycle']))->assertOk()->assertSee('Payment has not completed');
    $this->assertDatabaseCount('subscriptions', 0);
})->with([['open', 'unpaid'], ['complete', 'unpaid'], ['expired', 'unpaid']]);

test('payment failure removes paid growth limits and recovery restores them', function () {
    syncBillingLifecycle($this)->assertOk();
    $this->remote['status'] = 'past_due';
    syncBillingLifecycle($this)->assertOk();
    expect(app(UsageService::class)->planFor($this->owner->fresh()))->toBe('free');
    $this->get(route('billing.index'))->assertOk()->assertSee('Payment needs attention')->assertSee('Manage Subscription')->assertDontSee('Choose Pro');
    $this->remote['status'] = 'active';
    syncBillingLifecycle($this)->assertOk();
    expect(app(UsageService::class)->planFor($this->owner->fresh()))->toBe('pro');
});

test('scheduled cancellation uses item period end and resume clears it', function () {
    $this->remote['cancel_at_period_end'] = true;
    syncBillingLifecycle($this)->assertOk();
    expect($this->owner->fresh()->subscription()->ends_at->timestamp)->toBe($this->remote['items']['data'][0]['current_period_end']);
    $this->get(route('billing.index'))->assertSee('Pro ending');
    $this->remote['cancel_at_period_end'] = false;
    syncBillingLifecycle($this)->assertOk();
    expect($this->owner->fresh()->subscription()->ends_at)->toBeNull();
});

test('ended and expired access shows Free rather than a future cancellation', function () {
    $this->remote['status'] = 'canceled';
    $this->remote['ended_at'] = time() - 5;
    syncBillingLifecycle($this)->assertOk();
    $this->get(route('billing.index'))->assertSee('Free plan active')->assertDontSee('Pro ending');
    $this->owner->subscription()->update(['stripe_status' => 'active', 'ends_at' => now()->subMinute()]);
    expect(app(UsageService::class)->planFor($this->owner->fresh()))->toBe('free');
});

test('unknown price cannot grant Pro and changed items are replaced', function () {
    syncBillingLifecycle($this)->assertOk();
    $this->remote['items']['data'][0]['id'] = 'si_replacement';
    $this->remote['items']['data'][0]['price']['id'] = 'price_other';
    syncBillingLifecycle($this)->assertOk();
    $this->assertDatabaseMissing('subscription_items', ['stripe_id' => 'si_lifecycle']);
    expect(app(UsageService::class)->planFor($this->owner->fresh()))->toBe('free');
});

test('existing subscriptions and failed payments cannot start duplicate checkout', function ($status) {
    $this->remote['status'] = $status;
    $this->actingAs($this->owner)->postJson(route('billing.checkout'))->assertUnprocessable()->assertJsonValidationErrors('error');
    expect(collect($this->http->requests)->filter(fn ($r) => $r[0] === 'post' && $r[1] === '/v1/checkout/sessions'))->toHaveCount(0);
})->with(['active', 'trialing', 'past_due', 'unpaid', 'incomplete', 'paused']);

test('open checkout is reused instead of billing the customer twice', function () {
    $this->http->respond = function ($method, $path) {
        if ($path === '/v1/subscriptions') {
            return $this->http->collection([], $path);
        }
        if ($path === '/v1/checkout/sessions') {
            return $this->http->collection([array_merge($this->session, ['status' => 'open', 'url' => 'https://checkout.stripe.com/c/pay/test'])], $path);
        }

        return null;
    };
    $this->actingAs($this->owner)->post(route('billing.checkout'))->assertRedirect('https://checkout.stripe.com/c/pay/test');
});

test('signed subscription webhooks are idempotent and use current Stripe state', function () {
    $stale = lifecycleEvent($this);
    $this->remote['status'] = 'canceled';
    $this->remote['ended_at'] = time();
    billingWebhook($this, $stale)->assertOk();
    billingWebhook($this, $stale)->assertOk();
    $this->assertDatabaseCount('subscriptions', 1);
    $this->assertDatabaseCount('subscription_items', 1);
    expect(app(UsageService::class)->planFor($this->owner->fresh()))->toBe('free');
});

test('forged and wrong mode webhook events cannot change billing', function () {
    billingWebhook($this, lifecycleEvent($this), 'wrong')->assertStatus(403);
    $live = lifecycleEvent($this);
    $live['livemode'] = true;
    billingWebhook($this, $live)->assertStatus(400);
    $this->assertDatabaseCount('subscriptions', 0);
});

test('Stripe outage is not reported as successful sync', function () {
    $this->http->respond = fn () => [json_encode(['error' => ['type' => 'api_error', 'message' => 'Unavailable']]), 503, []];
    syncBillingLifecycle($this)->assertStatus(503)->assertJsonPath('success', false);
});

test('explicitly configured legacy Pro prices retain paid access', function () {
    config(['billing.legacy_pro_price_ids' => ['price_legacy']]);
    $this->remote['items']['data'][0]['price']['id'] = 'price_legacy';
    syncBillingLifecycle($this)->assertOk();
    expect(app(UsageService::class)->planFor($this->owner->fresh()))->toBe('pro');
});

test('checkout cancel page does not claim no charges or cancel an existing subscription', function () {
    syncBillingLifecycle($this)->assertOk();
    $this->get(route('billing.cancel'))->assertOk()
        ->assertSee('Leaving checkout does not cancel an existing subscription.')
        ->assertDontSee('No charges were made.')->assertDontSee('Nothing has been charged.');
    expect(app(UsageService::class)->planFor($this->owner->fresh()))->toBe('pro');
    expect($this->owner->fresh()->subscription()->ends_at)->toBeNull();
});
