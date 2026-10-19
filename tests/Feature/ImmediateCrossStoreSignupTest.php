<?php

use App\Mail\CustomerWelcomeEmail;
use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

test('new store signup isolates identity and collects stamps but must verify before redemption', function (string $environment) {
    config(['app.env' => $environment, 'app.url' => $environment === 'production' ? 'https://app.kawhe.shop' : 'https://testing.kawhe.shop']);
    Mail::fake(); Queue::fake();
    $old = LoyaltyAccount::factory()->create(['stamp_count' => 2, 'reward_balance' => 1, 'verified_at' => now()]);
    $old->customer->forceFill(['email' => 'same@example.invalid', 'phone' => '0123456789', 'birthday' => '1990-01-01', 'email_verified_at' => now()])->save();
    $owner = User::factory()->create();
    $store = Store::factory()->for($owner)->create(['reward_target' => 3, 'require_verification_for_redemption' => false]);
    $program = $store->resolvedDefaultProgram();
    $program->update(['require_verification_for_redemption' => false]);
    $join = route('join.store', ['slug' => $program->slug, 't' => $program->join_token]);
    $this->post($join, ['email' => 'same@example.invalid', 'name' => 'New supplied name'])->assertRedirect();
    $card = LoyaltyAccount::where('loyalty_program_id', $program->id)->sole();
    expect($card->customer_id)->not->toBe($old->customer_id);
    expect($card->verified_at)->toBeNull();
    expect($card->customer->phone)->toBeNull();
    expect($card->customer->birthday)->toBeNull();
    expect($card->customer->email_verified_at)->toBeNull();
    expect($card->requires_verification_for_redemption)->toBeTrue();
    $mail = Mail::queued(CustomerWelcomeEmail::class)->last();
    $verify = '/verify-email/'.$mail->verificationToken.'?card='.$card->public_token;
    $this->get('/c/'.$card->public_token)->assertOk()->assertDontSee($old->public_token);
    $this->actingAs($owner)->postJson('/stamp', ['store_id' => $store->id, 'token' => $card->public_token, 'count' => 3, 'idempotency_key' => 'earn-before-verification'])->assertOk();
    expect($card->fresh()->reward_balance)->toBe(1);
    $redeem = ['store_id' => $store->id, 'token' => $card->fresh()->redeem_token, 'idempotency_key' => 'verify-then-redeem'];
    $this->postJson('/redeem', $redeem)->assertStatus(422)->assertJsonPath('status', 'verification_required');
    $this->get($verify)->assertOk();
    expect($card->fresh()->verified_at)->toBeNull();
    $this->post($verify)->assertRedirect();
    $this->postJson('/redeem', $redeem)->assertOk();
    expect($card->fresh()->reward_balance)->toBe(0);
    expect($old->fresh()->stamp_count)->toBe(2);
    expect($old->fresh()->reward_balance)->toBe(1);
    $this->post($join, ['email' => 'same@example.invalid'])->assertOk()->assertViewIs('join.recovery-sent')->assertDontSee($card->public_token);
    expect(LoyaltyAccount::where('loyalty_program_id', $program->id)->count())->toBe(1);
})->with(['testing', 'production']);
