<?php

use App\Jobs\SendMerchantSignupAlert;
use App\Mail\MerchantSignupAlertEmail;
use App\Mail\MerchantWelcomeEmail;
use App\Models\MerchantSignupAlert;
use App\Models\Store;
use App\Models\User;
use App\Notifications\MerchantResetPassword;
use App\Notifications\MerchantVerifyEmail;
use App\Services\MerchantSignupAlerts;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

function growthRegistration(array $changes = []): array
{
    return array_merge(['name' => 'TEST Café Owner', 'email' => 'owner@example.invalid',
        'store_name' => 'TEST Growth Café', 'password' => 'local-test-password',
        'password_confirmation' => 'local-test-password'], $changes);
}

beforeEach(function () {
    config(['admin.signup_alert_email' => 'admin@example.invalid']);
    Mail::fake();
    Notification::fake();
    Queue::fake();
});

test('cafe list requires a verified platform administrator', function () {
    $this->get('/admin/merchants')->assertRedirect('/login');
    $this->actingAs(User::factory()->create())->get('/admin/merchants')->assertForbidden();
    $this->actingAs(User::factory()->unverified()->create(['is_super_admin' => true]))
        ->get('/admin/merchants')->assertRedirect(route('verification.notice'));
});

test('signup list shows owner setup plan and attribution with filters and private caching', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $owner = User::factory()->unverified()->create(['name' => 'Tui Owner']);
    $owner->forceFill(['signup_attribution' => ['utm_source' => 'sendgrid', 'utm_campaign' => 'cafe_launch']])->save();
    Store::factory()->create(['user_id' => $owner->id, 'name' => 'Tui Coffee', 'onboarding_step' => 'card_design', 'onboarding_completed_at' => null]);
    $ready = User::factory()->create(['name' => 'Ready Owner']);
    Store::factory()->create(['user_id' => $ready->id, 'onboarding_completed_at' => now()]);
    $response = $this->actingAs($admin)->get('/admin/merchants?q=Tui&verification=pending&setup=incomplete&campaign=cafe_launch');
    $response->assertOk()->assertSee('Tui Coffee')->assertSee('Choosing card design')
        ->assertSee('Awaiting email verification')->assertSee('Free')->assertSee('sendgrid')->assertDontSee('Ready Owner');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    $this->get('/admin/merchants?setup=ready&verification=verified')->assertSee('Ready Owner')->assertDontSee('Tui Owner');
});

test('cafe list is paginated and escapes merchant and campaign text', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $owner = User::factory()->create(['name' => '<script>alert(1)</script>']);
    $owner->forceFill(['signup_attribution' => ['utm_source' => '<img src=x onerror=alert(1)>']])->save();
    Store::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($admin)->get('/admin/merchants')->assertOk()->assertSee('&lt;script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<img src=x onerror=alert(1)>', false);
    User::factory()->count(21)->create()->each(fn ($u) => Store::factory()->create(['user_id' => $u->id]));
    $this->get('/admin/merchants')->assertViewHas('merchants', fn ($page) => $page->total() === 22 && $page->count() === 20);
});

test('signup preserves first tagged visit across validation and navigation but not arbitrary fields', function () {
    $this->get('/register?utm_source=sendgrid&utm_medium=email&utm_campaign=cafe_launch&secret_token=never-store')->assertOk();
    $this->get('/login?utm_source=later')->assertOk();
    $this->post('/register', growthRegistration(['email' => 'invalid']))->assertSessionHasErrors('email');
    $this->post('/register', growthRegistration(['signup_attribution' => ['utm_source' => 'forged'], 'is_super_admin' => true]))->assertRedirect();
    $owner = User::where('email', 'owner@example.invalid')->sole();
    expect($owner->signup_attribution)->toMatchArray(['utm_source' => 'sendgrid', 'utm_campaign' => 'cafe_launch'])
        ->not->toHaveKey('secret_token');
    expect($owner->is_super_admin)->toBeFalse();
    expect(session()->has('signup_attribution'))->toBeFalse();
    expect(MerchantSignupAlert::where('user_id', $owner->id)->where('event', 'registered')->count())->toBe(1);
    Notification::assertSentTo($owner, MerchantVerifyEmail::class);
    Mail::assertNotQueued(MerchantWelcomeEmail::class);
});

test('untagged signups stay unattributed and expired attribution is discarded', function () {
    $this->withSession(['signup_attribution' => ['utm_source' => 'expired', 'captured_at' => now()->subDays(31)->timestamp]])
        ->post('/register', growthRegistration())->assertRedirect();
    expect(User::where('email', 'owner@example.invalid')->sole()->signup_attribution)->toBeNull();
});

test('array and oversized campaign values cannot break registration', function () {
    $this->get('/register?utm_source[]=invalid&utm_campaign='.str_repeat('a', 180))->assertOk();
    $this->post('/register', growthRegistration())->assertRedirect();
    $tags = User::where('email', 'owner@example.invalid')->sole()->signup_attribution;
    expect($tags)->not->toHaveKey('utm_source');
    expect(strlen($tags['utm_campaign']))->toBe(120);
});

test('only confirmed email creates verified alert and repeated confirmation stays unique', function () {
    $this->post('/register', growthRegistration())->assertRedirect();
    $owner = User::where('email', 'owner@example.invalid')->sole();
    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $owner->id, 'hash' => sha1($owner->email)], false);
    $this->get($url)->assertOk();
    expect(MerchantSignupAlert::where('event', 'verified')->count())->toBe(0);
    $this->post($url)->assertRedirect();
    $this->post($url)->assertRedirect();
    expect(MerchantSignupAlert::where('event', 'verified')->count())->toBe(1);
    Mail::assertQueued(MerchantWelcomeEmail::class, 1);
});

test('alert job sends to configured admin once and does not expose customer or password data', function () {
    $owner = User::factory()->create(['name' => 'TEST Alert Owner']);
    Store::factory()->create(['user_id' => $owner->id, 'name' => 'TEST Alert Café']);
    $alert = MerchantSignupAlert::create(['user_id' => $owner->id, 'event' => 'registered']);
    $job = new SendMerchantSignupAlert($alert->id);
    $job->handle();
    $job->handle();
    Mail::assertSent(MerchantSignupAlertEmail::class, 1);
    Mail::assertSent(MerchantSignupAlertEmail::class, fn ($mail) => $mail->hasTo('admin@example.invalid') && ! $mail->hasTo($owner->email));
    expect($alert->fresh()->sent_at)->not->toBeNull();
    expect((new MerchantSignupAlertEmail($alert))->render())->toContain('TEST Alert Café')->not->toContain($owner->password);
});

test('failed email stays pending and a retry sends the same event without losing registration', function () {
    $owner = User::factory()->create();
    Store::factory()->create(['user_id' => $owner->id]);
    $alert = MerchantSignupAlert::create(['user_id' => $owner->id, 'event' => 'registered']);
    $mailFake = Mail::getFacadeRoot();
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Transport unavailable'));
    try {
        (new SendMerchantSignupAlert($alert->id))->handle();
    } catch (RuntimeException $e) {
    }
    expect($alert->fresh()->sent_at)->toBeNull();
    expect($alert->fresh()->attempts)->toBe(1);
    Mail::swap($mailFake);
    (new SendMerchantSignupAlert($alert->id))->handle();
    expect($alert->fresh()->sent_at)->not->toBeNull();
    Mail::assertSent(MerchantSignupAlertEmail::class, 1);
});

test('scheduler recovers unsent events and stops exhausted retries', function () {
    $owner = User::factory()->create();
    $alert = MerchantSignupAlert::create(['user_id' => $owner->id, 'event' => 'registered']);
    $failed = MerchantSignupAlert::create(['user_id' => $owner->id, 'event' => 'verified', 'attempts' => 3]);
    $this->artisan('admin:dispatch-signup-alerts')->assertSuccessful();
    Queue::assertPushed(SendMerchantSignupAlert::class, fn ($job) => $job->alertId === $alert->id);
    Queue::assertNotPushed(SendMerchantSignupAlert::class, fn ($job) => $job->alertId === $failed->id);
});

test('deleting a merchant also deletes attribution and pending alert records', function () {
    $owner = User::factory()->create();
    $alert = MerchantSignupAlert::create(['user_id' => $owner->id, 'event' => 'registered']);
    $owner->delete();
    expect(MerchantSignupAlert::find($alert->id))->toBeNull();
    (new SendMerchantSignupAlert($alert->id))->handle();
    Mail::assertNothingSent();
});

test('admin invitation never creates a cafe or bypasses email verification', function () {
    $this->artisan('kawhe:invite-superadmin', ['email' => 'platform@example.invalid'])->assertSuccessful();
    $admin = User::where('email', 'platform@example.invalid')->sole();
    expect($admin->isSuperAdmin())->toBeTrue();
    expect($admin->hasVerifiedEmail())->toBeFalse();
    expect($admin->stores()->count())->toBe(0);
    Notification::assertSentTo($admin, MerchantResetPassword::class);
    Notification::assertSentTo($admin, MerchantVerifyEmail::class);
    $this->artisan('kawhe:invite-superadmin', ['email' => $admin->email])->assertSuccessful();
    Notification::assertSentToTimes($admin, MerchantResetPassword::class, 1);
    expect(MerchantSignupAlert::count())->toBe(0);
    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $admin->id, 'hash' => sha1($admin->email)], false);
    $this->post($url)->assertOk();
    expect($admin->fresh()->hasVerifiedEmail())->toBeTrue();
    Mail::assertNotQueued(MerchantWelcomeEmail::class);
    $this->actingAs($admin->fresh())->get('/admin/merchants')->assertOk();
});

test('admin invite refuses to silently promote an existing merchant', function () {
    $owner = User::factory()->create(['email' => 'existing@example.invalid']);
    $this->artisan('kawhe:invite-superadmin', ['email' => $owner->email])->assertFailed();
    expect($owner->fresh()->isSuperAdmin())->toBeFalse();
    Notification::assertNothingSent();
});

test('staff and platform admins never trigger merchant signup alerts', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $staff = User::factory()->create(['is_staff_account' => true]);
    foreach ([$admin, $staff] as $user) {
        Store::factory()->create(['user_id' => $user->id]);
        app(MerchantSignupAlerts::class)->record($user, 'verified');
    }
    expect(MerchantSignupAlert::count())->toBe(0);
});
