<?php

use App\Jobs\UpdateWalletPassJob;
use App\Mail\CustomerCardRecovery;
use App\Mail\CustomerWelcomeEmail;
use App\Mail\MerchantWelcomeEmail;
use App\Mail\StoreStaffInvitationMail;
use App\Mail\VerifyCustomerEmail;
use App\Models\LoyaltyAccount;
use App\Models\User;
use App\Notifications\MerchantResetPassword;
use App\Notifications\MerchantVerifyEmail;
use App\Notifications\VerifyLoyaltyAccount;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

test('all customer and merchant emails use the independent email queue', function () {
    Queue::fake();
    $account = LoyaltyAccount::factory()->create();
    $user = User::factory()->create();
    foreach ([
        new CustomerCardRecovery('https://testing.kawhe.shop/login', 'QA'),
        new CustomerWelcomeEmail($account->customer, $account, 'qa-token'),
        new MerchantWelcomeEmail($user),
        new StoreStaffInvitationMail('https://testing.kawhe.shop/login', 'QA'),
        new VerifyCustomerEmail('qa-token', $account->public_token),
    ] as $mail) {
        expect($mail)->toBeInstanceOf(ShouldQueue::class);
        Mail::to('probe@example.test')->queue($mail);
    }
    Notification::send($user, new MerchantResetPassword('qa-token', $user->email));
    Notification::send($user, new MerchantVerifyEmail($user->email));
    Notification::send($account, new VerifyLoyaltyAccount($account));
    Queue::assertPushed(SendQueuedMailable::class, 5);
    Queue::assertPushed(SendQueuedNotifications::class, 3);
    foreach ([SendQueuedMailable::class, SendQueuedNotifications::class] as $class) {
        foreach (Queue::pushed($class) as $entry) {
            expect($entry->queue)->toBe('emails');
            expect($entry->tries)->toBe(3);
        }
    }
    Bus::dispatch(new UpdateWalletPassJob($account->id));
    Queue::assertPushed(UpdateWalletPassJob::class, fn ($job, $queue) => $queue !== 'emails');
});

test('smtp attempts have a finite timeout below the email worker timeout', function () {
    expect(config('mail.mailers.smtp.timeout'))->toBeGreaterThan(0)->toBeLessThan(60);
});
