<?php

use App\Mail\CustomerCardRecovery;
use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

test('same email joins different owners with isolated cards balances and merchant access', function (string $environment) {
    config(['app.env' => $environment, 'app.url' => $environment === 'production' ? 'https://app.kawhe.shop' : 'https://testing.kawhe.shop']);
    $as = function ($owner) {
        app('auth')->forgetGuards();

        return $this->actingAs($owner, 'web');
    };
    Mail::fake();
    Queue::fake();
    config(['cashier.price_id' => 'price_isolation']);
    $owners = [User::factory()->create(), User::factory()->create()];
    foreach ($owners as $owner) {
        $owner->forceFill(['stripe_id' => 'cus_isolation_'.$owner->id])->save();
        \Laravel\Cashier\Subscription::create(['user_id' => $owner->id, 'name' => 'default', 'stripe_id' => 'sub_isolation_'.$owner->id, 'stripe_status' => 'active', 'stripe_price' => 'price_isolation', 'quantity' => 1]);
    }
    $stores = [Store::factory()->for($owners[0])->create(['name' => 'TEST Merchant Alpha']), Store::factory()->for($owners[1])->create(['name' => 'TEST Merchant Beta'])];
    $programs = array_map(fn ($store) => $store->resolvedDefaultProgram(), $stores);
    foreach ($programs as $program) {
        $program->update(['reward_target' => 3]);
    }
    $join = fn ($program) => route('join.store', ['slug' => $program->slug, 't' => $program->join_token]);
    $email = 'same-customer@example.invalid';
    $this->post($join($programs[0]), ['email' => $email, 'name' => 'TEST Shared Customer'])->assertRedirect();
    $customer = Customer::where('email', $email)->sole();
    $a = $customer->loyaltyAccounts()->sole();
    $this->post($join($programs[1]), ['email' => $email, 'name' => 'New store name'])->assertRedirect();
    $b = LoyaltyAccount::where('store_id', $stores[1]->id)->sole();
    expect($b->customer_id)->not->toBe($a->customer_id);
    expect($b->verified_at)->toBeNull();
    expect($b->customer->email)->toBe($email);
    expect($a->customer->name)->toBe('TEST Shared Customer');
    $welcome = Mail::queued(\App\Mail\CustomerWelcomeEmail::class)->last();
    $verify = '/verify-email/'.$welcome->verificationToken.'?card='.$b->public_token;
    $this->get($verify)->assertOk();
    expect($b->fresh()->verified_at)->toBeNull();
    $this->post($verify)->assertRedirect();
    expect($a->public_token)->not->toBe($b->public_token);
    expect($a->wallet_auth_token)->not->toBe($b->wallet_auth_token);
    expect($a->store->user_id)->not->toBe($b->store->user_id);
    // Verify A through the same email-confirmation flow.
    $this->post($join($programs[0]), ['email' => $email])->assertOk();
    $this->post(Mail::queued(CustomerCardRecovery::class)->last()->recoveryUrl)->assertRedirect();
    $a->refresh();
    $b->refresh();
    foreach ([[$owners[0], $a, $b], [$owners[1], $b, $a]] as [$owner, $own, $foreign]) {
        $as($owner);
        $this->get(route('merchant.customers.index'))->assertOk()->assertViewHas('accounts', fn ($accounts) => $accounts->pluck('id')->all() === [$own->id]);
        $this->get(route('merchant.customers.index', ['store_id' => $foreign->store_id]))->assertNotFound();
        foreach (['show', 'edit'] as $action) {
            $this->get(route('merchant.customers.'.$action, $foreign))->assertRedirect(route('merchant.customers.index'));
        }
        $this->put(route('merchant.customers.update', $foreign), ['first_name' => 'Unauthorized'])->assertRedirect(route('merchant.customers.index'));
        foreach (['resend-verification', 'resend-welcome', 'sync-wallet'] as $action) {
            $this->post(route('merchant.customers.'.$action, $foreign))->assertRedirect(route('merchant.customers.index'));
        }
        $this->get(route('merchant.stores.programs.edit', [$foreign->store_id, $foreign->loyalty_program_id]))->assertNotFound();
        $as($owner)->getJson('/api/v1/stores')->assertOk()->assertJsonMissing(['id' => $foreign->store_id]);
        $csv = $as($owner)->get(route('merchant.customers.export'))->assertOk()->streamedContent();
        expect($csv)->toContain($own->store->name)->not->toContain($foreign->store->name, $foreign->manual_entry_code);
        foreach (['', '/api/v1'] as $prefix) {
            foreach (['scanner/preview', 'stamp', 'redeem/info', 'redeem'] as $action) {
                $response = $as($owner)->postJson($prefix.'/'.$action, ['store_id' => $foreign->store_id, 'token' => $foreign->public_token]);
                expect($response->status())->toBeIn([403, 404]);
                $response->assertJsonMissing(['customer_email' => $email]);
                $mixed = $as($owner)->postJson($prefix.'/'.$action, ['store_id' => $own->store_id, 'token' => $foreign->public_token]);
                expect($mixed->status())->toBeIn([403, 404]);
                $mixed->assertJsonMissing(['customer_email' => $email]);
            }
        }
        expect($foreign->fresh()->stamp_count)->toBe(0);
        expect($foreign->fresh()->reward_balance)->toBe(0);
    }
    $as($owners[0])->postJson('/api/v1/stamp', ['store_id' => $a->store_id, 'token' => $a->public_token, 'count' => 3, 'idempotency_key' => 'alpha-earn'])->assertOk();
    expect($a->fresh()->reward_balance)->toBe(1);
    expect($b->fresh()->reward_balance)->toBe(0);
    $as($owners[1])->postJson('/api/v1/stamp', ['store_id' => $b->store_id, 'token' => $b->public_token, 'count' => 2, 'idempotency_key' => 'beta-stamp'])->assertOk();
    expect($b->fresh()->stamp_count)->toBe(2);
    expect($a->fresh()->stamp_count)->toBe(0);
    $as($owners[0])->postJson('/api/v1/redeem', ['store_id' => $a->store_id, 'token' => $a->fresh()->redeem_token, 'idempotency_key' => 'alpha-redeem'])->assertOk();
    expect($a->fresh()->reward_balance)->toBe(0);
    expect($b->fresh()->stamp_count)->toBe(2);
    expect($b->fresh()->reward_balance)->toBe(0);
    $this->travel(6)->seconds();
    $as($owners[1])->postJson('/api/v1/stamp', ['store_id' => $b->store_id, 'token' => $b->public_token, 'count' => 1, 'idempotency_key' => 'beta-earn'])->assertOk();
    expect($b->fresh()->reward_balance)->toBe(1);
    expect($a->fresh()->reward_balance)->toBe(0);
    $as($owners[1])->postJson('/api/v1/redeem', ['store_id' => $b->store_id, 'token' => $b->fresh()->redeem_token, 'idempotency_key' => 'beta-redeem'])->assertOk();
    expect($b->fresh()->reward_balance)->toBe(0);
    expect($a->fresh()->stamp_count)->toBe(0);
    // Even an authorised contact edit on A must not alter B's shared identity.
    $as($owners[0])->put(route('merchant.customers.update', $a), ['first_name' => 'Alpha only', 'email' => 'alpha-only@example.invalid'])->assertRedirect();
    expect($b->fresh()->customer->email)->toBe($email);
    expect($a->fresh()->customer->email)->toBe('alpha-only@example.invalid');
})->with(['testing', 'production']);
