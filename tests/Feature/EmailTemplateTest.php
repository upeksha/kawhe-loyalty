<?php

use App\Mail\CustomerCardRecovery;
use App\Mail\CustomerWelcomeEmail;
use App\Mail\MerchantWelcomeEmail;
use App\Mail\StoreStaffInvitationMail;
use App\Mail\VerifyCustomerEmail;
use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use App\Models\User;
use App\Notifications\MerchantResetPassword;
use App\Notifications\MerchantVerifyEmail;
use App\Notifications\VerifyLoyaltyAccount;

test('all transactional emails render the shared layout and preserve their action links', function () {
    config(['app.url' => 'https://testing.kawhe.shop']);
    $user = User::factory()->create();
    $store = Store::factory()->create(['user_id' => $user->id, 'name' => 'Corner <Cafe>']);
    $customer = Customer::factory()->create(['name' => 'Alex']);
    $account = LoyaltyAccount::factory()->create(['store_id' => $store->id, 'customer_id' => $customer->id]);
    $messages = [
        new CustomerCardRecovery('https://testing.kawhe.shop/recover/test', $store->name),
        new StoreStaffInvitationMail('https://testing.kawhe.shop/invite/test', $store->name),
        new VerifyCustomerEmail('test-token', $account->public_token),
        new CustomerWelcomeEmail($customer, $account, 'test-token'),
        new MerchantWelcomeEmail($user),
        (new MerchantVerifyEmail($user->email))->toMail($user),
        (new MerchantResetPassword('test-token', $user->email))->toMail($user),
        (new VerifyLoyaltyAccount($account))->toMail($account),
    ];
    foreach ($messages as $message) {
        $html = (string) $message->render();
        expect($html)->toContain('LOYALTY', 'background-color:#2d5d47', 'role="presentation"', 'https://testing.kawhe.shop/', "If the button doesn't work");
        expect($html)->not->toContain('<Cafe>');
        expect(substr_count($html, '<!doctype html>'))->toBe(1);
    }
    expect($messages[2]->render())->toContain('24 hours');
    expect($messages[0]->render())->toContain('20 minutes');
    expect($messages[1]->render())->toContain('seven days');
});
