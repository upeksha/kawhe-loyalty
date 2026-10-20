<?php

use App\Mail\StoreStaffInvitationMail;
use App\Models\LoyaltyAccount;
use App\Models\PointsTransaction;
use App\Models\Store;
use App\Models\StoreStaff;
use App\Models\StoreStaffInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Mail::fake();
    Queue::fake([\App\Jobs\UpdateWalletPassJob::class]);
    $this->owner = User::factory()->create();
    $this->store = Store::factory()->for($this->owner)->create();
    $this->staff = User::factory()->create(['is_staff_account' => true]);
    $this->member = StoreStaff::create(['store_id' => $this->store->id, 'user_id' => $this->staff->id, 'can_stamp' => true, 'can_redeem' => true]);
    $this->account = LoyaltyAccount::factory()->create([
        'store_id' => $this->store->id, 'loyalty_program_id' => $this->store->resolvedDefaultProgram()->id,
        'verified_at' => now(), 'reward_balance' => 2, 'redeem_token' => 'REWARD123', 'stamp_count' => 0,
    ]);
});

function staffInvitationLink(): string
{
    return Mail::queued(StoreStaffInvitationMail::class)->last()->invitationUrl;
}

function createStaffInvite($test, string $email = 'newstaff@example.com')
{
    return $test->actingAs($test->owner)->from(route('merchant.staff.index'))->post(route('merchant.staff.invite', $test->store), ['email' => $email, 'can_stamp' => 1, 'can_redeem' => 1]);
}

function staffTokenFromLink(): string
{
    return basename(staffInvitationLink());
}

test('new staff invitations default to stamp permission only', function () {
    $response = $this->actingAs($this->owner)->get(route('merchant.staff.index'))->assertOk();
    $inviteForm = explode('Invitations expire in seven days.', explode('Invite a staff member', $response->getContent(), 2)[1], 2)[0];

    expect($inviteForm)->toMatch('/name="can_stamp" value="1" checked/');
    expect($inviteForm)->not->toMatch('/name="can_redeem" value="1" checked/');
});

test('owner can invite staff with only selected permissions and token is stored hashed', function () {
    $this->actingAs($this->owner)->post(route('merchant.staff.invite', $this->store), ['email' => 'NEWSTAFF@example.com', 'can_stamp' => 1])->assertRedirect();
    Mail::assertQueued(StoreStaffInvitationMail::class, fn ($mail) => $mail->hasTo('newstaff@example.com'));
    $invite = StoreStaffInvitation::firstOrFail();
    expect($invite->can_stamp)->toBeTrue()->and($invite->can_redeem)->toBeFalse();
    expect($invite->token_hash)->toBe(hash('sha256', staffTokenFromLink()));
    $this->assertDatabaseMissing('store_staff_invitations', ['token_hash' => staffTokenFromLink()]);
});

test('new staff accepts invitation without store setup and cannot forge their role or email', function () {
    createStaffInvite($this);
    $url = staffInvitationLink();
    auth()->logout();
    session()->invalidate();
    $this->get($url)->assertOk()->assertSee('Accept invitation');
    $this->post($url, ['name' => 'Barista', 'password' => 'test-password-123', 'password_confirmation' => 'test-password-123', 'email' => 'attacker@example.com', 'is_super_admin' => 1])
        ->assertRedirect(route('merchant.scanner'));
    $staff = User::where('email', 'newstaff@example.com')->firstOrFail();
    expect($staff->is_staff_account)->toBeTrue()->and($staff->is_super_admin)->toBeFalse()->and($staff->hasVerifiedEmail())->toBeTrue();
    expect($staff->stores()->count())->toBe(0)->and($staff->scannerStores()->count())->toBe(1);
    $this->get('/dashboard')->assertRedirect(route('merchant.scanner'));
    $this->get($url)->assertStatus(410);
    $this->post($url)->assertStatus(410);
});

test('existing account must sign in with the invited verified email', function () {
    $existing = User::factory()->create(['email' => 'existingstaff@example.com']);
    createStaffInvite($this, $existing->email);
    $url = staffInvitationLink();
    auth()->logout();
    session()->invalidate();
    $this->get($url)->assertOk()->assertSee('Sign in');
    $this->post($url, ['name' => 'Overwrite', 'password' => 'password', 'password_confirmation' => 'password'])->assertForbidden();
    $this->actingAs($this->staff)->post($url)->assertForbidden();
    $this->actingAs($existing)->post($url)->assertRedirect(route('merchant.scanner'));
    expect($existing->scannerStores()->count())->toBe(1);
    expect($existing->fresh()->name)->not->toBe('Overwrite');
});

test('unverified existing account must verify before accepting', function () {
    $existing = User::factory()->unverified()->create();
    createStaffInvite($this, $existing->email);
    $this->actingAs($existing)->get(staffInvitationLink())->assertOk()->assertSee('Verify email');
    $this->post(staffInvitationLink())->assertForbidden();
    expect(StoreStaff::where('user_id', $existing->id)->exists())->toBeFalse();
});

test('expired cancelled archived and forged invitations cannot be accepted', function () {
    createStaffInvite($this);
    $url = staffInvitationLink();
    auth()->logout();
    session()->invalidate();
    $invite = StoreStaffInvitation::firstOrFail();
    $invite->update(['expires_at' => now()->subSecond()]);
    $this->get($url)->assertStatus(410);
    $this->post($url)->assertStatus(410);
    $invite->update(['expires_at' => now()->addDay()]);
    $this->store->delete();
    $this->get($url)->assertStatus(410);
    $this->store->restore();
    $this->actingAs($this->owner)->delete(route('merchant.staff.cancel', [$this->store, $invite]))->assertRedirect();
    $this->get($url)->assertStatus(410);
    $this->get(route('staff.invitation', ['token' => str_repeat('z', 64)]))->assertStatus(410);
});

test('resending rotates invitation links without granting access before acceptance', function () {
    createStaffInvite($this);
    $first = staffInvitationLink();
    createStaffInvite($this);
    $second = staffInvitationLink();
    expect($second)->not->toBe($first);
    $this->get($first)->assertStatus(410);
    $this->get($second)->assertOk();
    expect(StoreStaffInvitation::count())->toBe(1);
    expect(User::where('email', 'newstaff@example.com')->exists())->toBeFalse();
});

test('only owners can manage staff and another store member cannot be modified', function () {
    $other = Store::factory()->create();
    $this->actingAs($this->staff)->post(route('merchant.staff.invite', $this->store), ['email' => 'stranger@example.com', 'can_stamp' => 1])->assertForbidden();
    $this->actingAs($this->owner)->post(route('merchant.staff.invite', $other), ['email' => 'stranger@example.com', 'can_stamp' => 1])->assertForbidden();
    $ownedOther = Store::factory()->for($this->owner)->create();
    $this->patch(route('merchant.staff.update', [$ownedOther, $this->member]), ['can_stamp' => 1])->assertNotFound();
    $this->delete(route('merchant.staff.revoke', [$ownedOther, $this->member]))->assertNotFound();
    Mail::assertNothingQueued();
});

test('inviting current members or requesting no permissions is rejected', function () {
    createStaffInvite($this, $this->staff->email)->assertSessionHasErrors('email');
    $this->post(route('merchant.staff.invite', $this->store), ['email' => 'none@example.com'])->assertSessionHasErrors('permissions');
    $this->patch(route('merchant.staff.update', [$this->store, $this->member]), [])->assertSessionHasErrors('permissions');
});

test('queue failure removes the unusable invitation', function () {
    Mail::shouldReceive('to')->once()->andReturnSelf();
    Mail::shouldReceive('queue')->once()->andThrow(new \RuntimeException('Unavailable'));
    createStaffInvite($this)->assertSessionHasErrors('email');
    expect(StoreStaffInvitation::count())->toBe(0);
});

test('staff can stamp and redeem and the ledger attributes actions to them', function () {
    $this->actingAs($this->staff)->postJson(route('stamp.store'), ['token' => $this->account->public_token, 'store_id' => $this->store->id])->assertOk()->assertJsonPath('success', true);
    expect($this->account->fresh()->stamp_count)->toBe(1);
    $this->postJson(route('redeem.store'), ['token' => 'REWARD123', 'store_id' => $this->store->id])->assertOk()->assertJsonPath('success', true);
    expect($this->account->fresh()->reward_balance)->toBe(1);
    expect(PointsTransaction::where('user_id', $this->staff->id)->count())->toBe(2);
});

test('stamp only staff cannot redeem through any endpoint including prefixed stamp requests', function () {
    $this->member->update(['can_redeem' => false]);
    $this->actingAs($this->staff)->postJson(route('scanner.preview'), ['token' => $this->account->public_token, 'store_id' => $this->store->id])->assertOk()->assertJsonPath('can_stamp', true)->assertJsonPath('can_redeem', false);
    foreach (['redeem.info', 'redeem.store'] as $route) {
        $this->postJson(route($route), ['token' => 'REWARD123', 'store_id' => $this->store->id])->assertForbidden();
    }
    $this->postJson(route('stamp.store'), ['token' => 'LR:REWARD123', 'store_id' => $this->store->id])->assertForbidden();
    expect($this->account->fresh()->reward_balance)->toBe(2);
});

test('redeem only staff cannot stamp and can redeem', function () {
    $this->member->update(['can_stamp' => false]);
    $this->actingAs($this->staff)->postJson(route('stamp.store'), ['token' => $this->account->public_token, 'store_id' => $this->store->id])->assertForbidden();
    $this->postJson(route('redeem.store'), ['token' => 'REWARD123', 'store_id' => $this->store->id])->assertOk();
    expect($this->account->fresh()->stamp_count)->toBe(0);
});

test('staff cannot access other stores by token manual code or store switching', function () {
    $foreign = LoyaltyAccount::factory()->create(['reward_balance' => 1, 'redeem_token' => 'FOREIGN123']);
    $this->actingAs($this->staff)->postJson(route('stamp.store'), ['token' => $foreign->public_token, 'store_id' => $this->store->id])->assertForbidden();
    $this->postJson(route('scanner.preview'), ['token' => $foreign->public_token])->assertForbidden();
    $this->postJson(route('redeem.store'), ['token' => 'FOREIGN123', 'store_id' => $foreign->store_id])->assertForbidden();
    $this->postJson(route('stamp.store'), ['token' => $foreign->manual_entry_code, 'store_id' => $foreign->store_id])->assertForbidden();
});

test('revocation takes effect for existing sessions and API tokens', function () {
    $token = $this->staff->createToken('staff-phone')->plainTextToken;
    $this->actingAs($this->owner)->delete(route('merchant.staff.revoke', [$this->store, $this->member]))->assertRedirect();
    auth()->logout();
    session()->invalidate();
    $this->withToken($token)->getJson('/api/v1/stores')->assertOk()->assertJsonCount(0, 'stores');
    $this->withToken($token)->postJson('/api/v1/stamp', ['token' => $this->account->public_token, 'store_id' => $this->store->id])->assertForbidden();
    $this->actingAs($this->staff, 'web')->get(route('merchant.scanner'))->assertOk()->assertSee('No active staff access');
    $this->get('/dashboard')->assertRedirect(route('merchant.scanner'));
});

test('permission edits apply to API calls without signing in again', function () {
    $token = $this->staff->createToken('staff-phone')->plainTextToken;
    $this->actingAs($this->owner)->patch(route('merchant.staff.update', [$this->store, $this->member]), ['can_redeem' => 1])->assertRedirect();
    auth()->logout();
    session()->invalidate();
    $this->withToken($token)->getJson('/api/v1/stores')->assertOk()->assertJsonCount(1, 'stores')->assertJsonPath('stores.0.can_stamp', false)->assertJsonPath('stores.0.can_redeem', true);
    $this->withToken($token)->postJson('/api/v1/stamp', ['token' => $this->account->public_token, 'store_id' => $this->store->id])->assertForbidden();
    $this->withToken($token)->postJson('/api/v1/redeem', ['token' => 'REWARD123', 'store_id' => $this->store->id])->assertOk();
});

test('staff cannot access business settings billing exports onboarding or staff management', function (string $path) {
    $this->actingAs($this->staff)->get($path)->assertForbidden();
})->with(['/merchant/stores', '/merchant/customers/export', '/merchant/staff', '/merchant/dashboard', '/merchant/onboarding/wizard/store-basics', '/billing']);

test('staff scanner navigation hides owner controls and keeps profile available', function () {
    $this->actingAs($this->staff)->get(route('merchant.scanner'))->assertOk()->assertDontSee('Staff access')->assertDontSee('href="'.route('billing.index').'"', false);
    $this->get('/profile')->assertOk();
});

test('archived stores remove staff scanner and action access', function () {
    $this->store->delete();
    $this->actingAs($this->staff, 'web')->get(route('merchant.scanner'))->assertOk()->assertSee('No active staff access');
    $this->postJson(route('stamp.store'), ['token' => $this->account->public_token])->assertForbidden();
    $this->postJson(route('redeem.store'), ['token' => 'REWARD123', 'store_id' => $this->store->id])->assertForbidden();
});

test('staff assignments never broaden an existing merchants ownership rights', function () {
    $anotherOwner = User::factory()->create();
    Store::factory()->for($anotherOwner)->create();
    StoreStaff::create(['store_id' => $this->store->id, 'user_id' => $anotherOwner->id, 'can_stamp' => true, 'can_redeem' => true]);
    $this->actingAs($anotherOwner)->get(route('merchant.stores.edit', $this->store))->assertNotFound();
    $this->post(route('merchant.staff.invite', $this->store), ['email' => 'attack@example.com', 'can_stamp' => 1])->assertForbidden();
    $this->postJson(route('stamp.store'), ['token' => $this->account->public_token])->assertOk();
});
