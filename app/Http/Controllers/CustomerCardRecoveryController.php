<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyProgram;
use App\Services\Billing\UsageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerCardRecoveryController extends Controller
{
    public function __invoke(Request $request, string $token)
    {
        $response = DB::transaction(function () use ($request, $token) {
            $query = DB::table('customer_card_recoveries')->where('token_hash', hash('sha256', $token));
            $recovery = (clone $query)->lockForUpdate()->first();
            if (! $recovery || now()->gte($recovery->expires_at)) {
                return response()->view('join.recovery-invalid', [], 410);
            }

            $customer = Customer::whereKey($recovery->customer_id)->lockForUpdate()->first();
            $program = LoyaltyProgram::with('store')->find($recovery->loyalty_program_id);
            $store = $program?->store;
            if (! $customer?->email || ! hash_equals($recovery->email_hash, hash('sha256', $customer->email)) || ! $store || ! $program || $store->trashed()) {
                return response()->view('join.recovery-invalid', [], 410);
            }

            // Email scanners may follow GET links. Only an explicit CSRF-protected POST consumes it.
            if (! $request->isMethod('post')) {
                return response()->view('join.recovery-confirm', compact('program', 'store', 'token'));
            }

            $account = LoyaltyAccount::where('loyalty_program_id', $program->id)
                ->where('customer_id', $customer->id)->first();
            if (! $account) {
                if (! $recovery->allow_enrollment) {
                    return response()->view('join.recovery-invalid', [], 410);
                }
                if (! $store->user || ! app(UsageService::class)->canAcceptNewCustomer($program)) {
                    $token = $program->join_token;

                    return response()->view('join.limit-reached', compact('store', 'program', 'token'));
                }
                $account = LoyaltyAccount::create([
                    'store_id' => $store->id,
                    'loyalty_program_id' => $program->id,
                    'customer_id' => $customer->id,
                ]);
            }

            $account->update([
                'verified_at' => $account->verified_at ?? now(),
                'email_verification_token_hash' => null,
                'email_verification_expires_at' => null,
            ]);

            // Remove every outstanding link for this customer/program in the same transaction.
            DB::table('customer_card_recoveries')->where('customer_id', $customer->id)
                ->where('loyalty_program_id', $program->id)->delete();

            return redirect()->route('card.show', ['public_token' => $account->public_token])
                ->with('show_wallet_nudge', true);
        }, 3);

        return $response->withHeaders(['Cache-Control' => 'no-store, private', 'Referrer-Policy' => 'no-referrer']);
    }
}
