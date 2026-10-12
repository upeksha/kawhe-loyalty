<?php

namespace App\Services\Customers;

use App\Mail\CustomerCardRecovery;
use App\Models\Customer;
use App\Models\LoyaltyProgram;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class CardRecoveryService
{
    public function send(Customer $customer, LoyaltyProgram $program, bool $allowEnrollment = false): void
    {
        if (! $customer->email) {
            return;
        }

        // Shared across signup, lookup, programs and IP addresses to limit inbox abuse.
        $key = 'card-recovery:'.hash('sha256', strtolower($customer->email));
        if (RateLimiter::tooManyAttempts($key, 2)) {
            return;
        }
        RateLimiter::hit($key, 600);

        $token = Str::random(64);
        $hash = hash('sha256', $token);
        DB::table('customer_card_recoveries')->where('expires_at', '<', now())->delete();
        DB::table('customer_card_recoveries')->insert([
            'token_hash' => $hash,
            'customer_id' => $customer->id,
            'loyalty_program_id' => $program->id,
            'email_hash' => hash('sha256', $customer->email),
            'allow_enrollment' => $allowEnrollment,
            'expires_at' => now()->addMinutes(20),
            'created_at' => now(),
        ]);

        // Use the configured origin, never an untrusted request Host header.
        $url = rtrim(config('app.url'), '/').route('customer.card.recover', ['token' => $token], false);
        try {
            Mail::to($customer->email)->queue(new CustomerCardRecovery($url, $program->store->name));
        } catch (\Throwable $e) {
            DB::table('customer_card_recoveries')->where('token_hash', $hash)->delete();
            Log::error('Customer card recovery could not be queued', ['customer_id' => $customer->id]);
        }
    }
}
