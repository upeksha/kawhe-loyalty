<?php

use App\Models\Store;
use App\Models\User;
use Stripe\ApiRequestor;
use Stripe\HttpClient\CurlClient;
use Tests\Support\BillingStripeHttpFake;

beforeEach(function () {
    config(['cashier.secret' => 'sk_test_fake_never_network']);
    $this->stripeHttp = new BillingStripeHttpFake;
    ApiRequestor::setHttpClient($this->stripeHttp);
    $this->merchant = User::factory()->create(['stripe_id' => 'cus_deletion_test']);
    $this->store = Store::factory()->for($this->merchant)->create();
    $this->merchant->createToken('test-device');
});

afterEach(function () {
    ApiRequestor::setHttpClient(new CurlClient);
});

function deleteBillingTestAccount($test)
{
    return $test->actingAs($test->merchant)->from('/profile')->delete('/profile', ['password' => 'password']);
}

test('active trialing delinquent incomplete paused and grace period subscriptions block deletion', function (string $status) {
    $this->stripeHttp->respond = fn ($method, $path) => $path === '/v1/subscriptions'
        ? $this->stripeHttp->collection([['id' => 'sub_remote', 'object' => 'subscription', 'status' => $status, 'cancel_at_period_end' => true]], $path) : null;
    deleteBillingTestAccount($this)->assertRedirect('/profile')->assertSessionHasErrorsIn('userDeletion', 'billing');
    expect($this->merchant->fresh())->not->toBeNull();
    expect($this->store->fresh())->not->toBeNull();
    expect($this->merchant->tokens()->count())->toBe(1);
    $this->assertAuthenticated();
    expect(collect($this->stripeHttp->requests)->where(0, 'delete'))->toHaveCount(0);
})->with(['active', 'trialing', 'past_due', 'unpaid', 'incomplete', 'paused', 'unknown_future_status']);

test('remote subscription pagination is checked even when local billing is empty', function () {
    $this->stripeHttp->respond = function ($method, $path, $params) {
        if ($path !== '/v1/subscriptions') {
            return null;
        }
        if (! isset($params['starting_after'])) {
            return $this->stripeHttp->collection([['id' => 'sub_old', 'object' => 'subscription', 'status' => 'canceled']], $path, true);
        }
        expect($params['starting_after'])->toBe('sub_old');

        return $this->stripeHttp->collection([['id' => 'sub_active', 'object' => 'subscription', 'status' => 'active']], $path);
    };
    deleteBillingTestAccount($this)->assertSessionHasErrorsIn('userDeletion', 'billing');
    expect($this->merchant->fresh())->not->toBeNull();
});

test('pending billing resources block deletion', function (string $path, array $item) {
    $this->stripeHttp->respond = fn ($method, $requestPath) => $requestPath === $path
        ? $this->stripeHttp->collection([$item], $path) : null;
    deleteBillingTestAccount($this)->assertSessionHasErrorsIn('userDeletion', 'billing');
    expect($this->merchant->fresh())->not->toBeNull();
    expect(collect($this->stripeHttp->requests)->where(0, 'delete'))->toHaveCount(0);
})->with([
    'scheduled subscription' => ['/v1/subscription_schedules', ['id' => 'sched_future', 'object' => 'subscription_schedule', 'status' => 'not_started']],
    'invoice' => ['/v1/invoices', ['id' => 'in_due', 'object' => 'invoice', 'status' => 'open']],
    'invoice item' => ['/v1/invoiceitems', ['id' => 'ii_pending', 'object' => 'invoiceitem']],
    'open checkout' => ['/v1/checkout/sessions', ['id' => 'cs_open', 'object' => 'checkout.session', 'status' => 'open', 'payment_status' => 'unpaid']],
    'asynchronous payment' => ['/v1/checkout/sessions', ['id' => 'cs_processing', 'object' => 'checkout.session', 'status' => 'complete', 'payment_status' => 'unpaid']],
]);

test('Stripe failure preserves account session and billing records', function () {
    $this->stripeHttp->respond = fn () => [json_encode(['error' => ['type' => 'api_error', 'message' => 'Unavailable']]), 503, []];
    deleteBillingTestAccount($this)->assertSessionHasErrorsIn('userDeletion', 'billing');
    expect($this->merchant->fresh())->not->toBeNull();
    $this->assertAuthenticated();
});

test('customer closure failure prevents local deletion', function () {
    $this->stripeHttp->respond = fn ($method) => $method === 'delete'
        ? [json_encode(['error' => ['type' => 'api_error', 'message' => 'Unavailable']]), 503, []] : null;
    deleteBillingTestAccount($this)->assertSessionHasErrorsIn('userDeletion', 'billing');
    expect($this->merchant->fresh())->not->toBeNull();
});

test('cleared billing closes Stripe before deleting local account and revokes tokens', function () {
    $subscription = $this->merchant->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_old', 'stripe_status' => 'canceled']);
    $subscription->items()->create(['stripe_id' => 'si_old', 'stripe_product' => 'prod_old', 'stripe_price' => 'price_old', 'quantity' => 1]);
    $this->stripeHttp->respond = fn ($method, $path) => $path === '/v1/subscriptions'
        ? $this->stripeHttp->collection([['id' => 'sub_old', 'object' => 'subscription', 'status' => 'canceled']], $path) : null;
    deleteBillingTestAccount($this)->assertRedirect('/')->assertSessionHasNoErrors();
    expect($this->merchant->fresh())->toBeNull();
    $this->assertGuest();
    $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $this->merchant->id]);
    $this->assertDatabaseMissing('subscriptions', ['id' => $subscription->id]);
    $this->assertDatabaseMissing('subscription_items', ['subscription_id' => $subscription->id]);
    expect(collect($this->stripeHttp->requests)->last()[0])->toBe('delete');
});

test('already deleted Stripe customer permits safe local retry', function () {
    $this->stripeHttp->respond = fn () => [json_encode(['id' => 'cus_deletion_test', 'object' => 'customer', 'deleted' => true]), 200, []];
    deleteBillingTestAccount($this)->assertRedirect('/');
    expect($this->merchant->fresh())->toBeNull();
    expect($this->stripeHttp->requests)->toHaveCount(1);
});

test('local billing with a missing customer mapping fails closed', function () {
    $this->merchant->forceFill(['stripe_id' => null])->save();
    $this->merchant->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_orphan', 'stripe_status' => 'active']);
    deleteBillingTestAccount($this)->assertSessionHasErrorsIn('userDeletion', 'billing');
    expect($this->stripeHttp->requests)->toBeEmpty();
    expect($this->merchant->fresh())->not->toBeNull();
});

test('incorrect password does not contact Stripe', function () {
    $this->actingAs($this->merchant)->delete('/profile', ['password' => 'wrong'])
        ->assertSessionHasErrorsIn('userDeletion', 'password');
    expect($this->stripeHttp->requests)->toBeEmpty();
});

test('checkout portal sync and deletion share the billing serialization guard', function () {
    foreach (['billing.checkout', 'billing.portal', 'billing.sync', 'billing.success', 'profile.destroy'] as $name) {
        expect(app('router')->getRoutes()->getByName($name)->gatherMiddleware())
            ->toContain(\App\Http\Middleware\SerializeBillingChanges::class);
    }
});
