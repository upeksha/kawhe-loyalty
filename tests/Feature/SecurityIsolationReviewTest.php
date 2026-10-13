<?php

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    Mail::fake();
    $this->withoutVite();
});

test('editing a shared customer only changes the selected card and resets its email proof', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $owner->id]);
    $otherStore = Store::factory()->create();
    $customer = Customer::factory()->create(['email' => 'original@example.test']);
    $account = LoyaltyAccount::factory()->create(['store_id' => $store->id, 'loyalty_program_id' => $store->resolvedDefaultProgram()->id, 'customer_id' => $customer->id, 'verified_at' => now(), 'email_verification_token_hash' => hash('sha256', 'old-link'), 'email_verification_expires_at' => now()->addDay()]);
    $other = LoyaltyAccount::factory()->create(['store_id' => $otherStore->id, 'loyalty_program_id' => $otherStore->resolvedDefaultProgram()->id, 'customer_id' => $customer->id, 'verified_at' => now()]);

    $this->actingAs($owner)->put(route('merchant.customers.update', $account), ['email' => 'changed@example.test', 'first_name' => 'Changed'])->assertSessionHasNoErrors();

    expect($other->fresh()->customer->email)->toBe('original@example.test')
        ->and($other->fresh()->verified_at)->not->toBeNull()
        ->and($account->fresh()->customer_id)->not->toBe($customer->id)
        ->and($account->fresh()->customer->email)->toBe('changed@example.test')
        ->and($account->fresh()->verified_at)->toBeNull()
        ->and($account->fresh()->email_verification_token_hash)->toBeNull();
});

test('recovery finds the customer belonging to this program even when another record has the same email', function () {
    $store = Store::factory()->create();
    $program = $store->resolvedDefaultProgram();
    Customer::factory()->create(['email' => 'shared@example.test']);
    $customer = Customer::factory()->create(['email' => 'shared@example.test']);
    LoyaltyAccount::factory()->create(['store_id' => $store->id, 'loyalty_program_id' => $program->id, 'customer_id' => $customer->id]);
    $this->post(route('join.lookup', ['slug' => $program->slug, 't' => $program->join_token]), ['email' => $customer->email])->assertOk();
    $this->assertDatabaseHas('customer_card_recoveries', ['customer_id' => $customer->id, 'loyalty_program_id' => $program->id]);
});

test('rejoining prefers the existing card identity over another cafe record', function () {
    $store = Store::factory()->create();
    $program = $store->resolvedDefaultProgram();
    Customer::factory()->create(['email' => 'shared@example.test']);
    $customer = Customer::factory()->create(['email' => 'shared@example.test']);
    LoyaltyAccount::factory()->create(['store_id' => $store->id, 'loyalty_program_id' => $program->id, 'customer_id' => $customer->id]);
    $this->post(route('join.store', ['slug' => $program->slug, 't' => $program->join_token]), ['email' => $customer->email, 'name' => 'Customer', 'first_name' => 'Customer'])->assertOk();
    $this->assertDatabaseHas('customer_card_recoveries', ['customer_id' => $customer->id, 'loyalty_program_id' => $program->id]);
});

test('password reset revokes existing mobile tokens without revoking other users', function () {
    $user = User::factory()->create();
    $user->createToken('iphone');
    $user->createToken('other-phone');
    $other = User::factory()->create();
    $other->createToken('phone');
    $this->post('/reset-password', ['email' => $user->email, 'token' => Password::createToken($user), 'password' => 'changed-password', 'password_confirmation' => 'changed-password'])->assertSessionHasNoErrors();
    expect($user->tokens()->count())->toBe(0)->and($other->tokens()->count())->toBe(1);
});

test('password change revokes mobile tokens but invalid attempts do not', function () {
    $user = User::factory()->create();
    $user->createToken('iphone');
    $this->actingAs($user)->put('/password', ['current_password' => 'wrong', 'password' => 'changed-password', 'password_confirmation' => 'changed-password'])->assertSessionHasErrorsIn('updatePassword');
    expect($user->tokens()->count())->toBe(1);
    $this->put('/password', ['current_password' => 'password', 'password' => 'changed-password', 'password_confirmation' => 'changed-password'])->assertSessionHasNoErrors();
    expect($user->tokens()->count())->toBe(0);
});

test('a browser session with an old password cannot access the profile', function () {
    $user = User::factory()->create();
    $oldPassword = $user->password;
    $user->update(['password' => 'new-password']);
    $this->actingAs($user)->withSession(['password_hash_web' => $oldPassword])->get('/profile')->assertRedirect('/login');
    $this->assertGuest();
});

test('a cafe owner cannot preview stamp or redeem another cafes card through web or API', function (string $prefix) {
    $owner = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $owner->id]);
    $foreignStore = Store::factory()->create();
    $account = LoyaltyAccount::factory()->create(['store_id' => $foreignStore->id, 'loyalty_program_id' => $foreignStore->resolvedDefaultProgram()->id, 'reward_balance' => 1, 'redeem_token' => 'foreign-reward', 'stamp_count' => 0, 'verified_at' => now()]);
    $this->actingAs($owner);
    $this->postJson($prefix.'/scanner/preview', ['token' => $account->public_token])->assertForbidden();
    $this->postJson($prefix.'/stamp', ['token' => $account->public_token, 'store_id' => $store->id])->assertForbidden();
    $this->postJson($prefix.'/redeem', ['token' => 'foreign-reward', 'store_id' => $foreignStore->id])->assertForbidden();
    expect($account->fresh()->stamp_count)->toBe(0)->and($account->fresh()->reward_balance)->toBe(1);
})->with(['', '/api/v1']);

test('a revoked mobile token is rejected at the authentication boundary', function () {
    $user = User::factory()->create();
    $token = $user->createToken('phone')->plainTextToken;
    $this->post('/reset-password', ['email' => $user->email, 'token' => Password::createToken($user), 'password' => 'changed-password', 'password_confirmation' => 'changed-password'])->assertSessionHasNoErrors();
    auth()->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/stores')->assertUnauthorized();
});

test('changing an unshared customer email invalidates old verification and recovery links', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $owner->id]);
    $program = $store->resolvedDefaultProgram();
    $customer = Customer::factory()->create(['email' => 'original@example.test']);
    $account = LoyaltyAccount::factory()->create(['store_id' => $store->id, 'loyalty_program_id' => $program->id, 'customer_id' => $customer->id, 'verified_at' => now(), 'email_verification_token_hash' => hash('sha256', 'old-link'), 'email_verification_expires_at' => now()->addDay()]);
    $this->post(route('join.lookup', ['slug' => $program->slug, 't' => $program->join_token]), ['email' => $customer->email])->assertOk();
    $url = Mail::queued(\App\Mail\CustomerCardRecovery::class)->last()->recoveryUrl;
    $this->actingAs($owner)->put(route('merchant.customers.update', $account), ['email' => 'changed@example.test'])->assertSessionHasNoErrors();
    expect($account->fresh()->verified_at)->toBeNull()->and($account->fresh()->email_verification_token_hash)->toBeNull();
    $this->post($url)->assertStatus(410);
    $this->post('/verify-email/old-link')->assertRedirect();
    expect($account->fresh()->verified_at)->toBeNull();
});
