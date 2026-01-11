<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyAccount;
use App\Models\PointsTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CardController extends Controller
{
    public function show(string $public_token)
    {
        $account = LoyaltyAccount::with(['store', 'customer'])
            ->where('public_token', $public_token)
            ->firstOrFail();

        // Check if reward should be available but isn't set (migration fix)
        if ($account->stamp_count >= $account->store->reward_target && is_null($account->reward_available_at) && is_null($account->reward_redeemed_at)) {
            $account->reward_available_at = now();
            $account->redeem_token = Str::random(40);
            $account->save();
        }

        // Fix for accounts stuck in "Redeemed" state but have started a new cycle
        if (!is_null($account->reward_redeemed_at) && $account->stamp_count > 0 && $account->stamp_count < $account->store->reward_target) {
            $account->reward_redeemed_at = null;
            $account->save();
        }

        return view('card.show', compact('account'));
    }

    public function api(string $public_token)
    {
        $account = LoyaltyAccount::with(['store', 'customer'])
            ->where('public_token', $public_token)
            ->firstOrFail();

        // Check if reward should be available but isn't set (migration fix)
        if ($account->stamp_count >= $account->store->reward_target && is_null($account->reward_available_at) && is_null($account->reward_redeemed_at)) {
            $account->reward_available_at = now();
            $account->redeem_token = Str::random(40);
            $account->save();
        }

        // Fix for accounts stuck in "Redeemed" state but have started a new cycle
        if (!is_null($account->reward_redeemed_at) && $account->stamp_count > 0 && $account->stamp_count < $account->store->reward_target) {
            $account->reward_redeemed_at = null;
            $account->save();
        }

        // Refresh the account to get latest data
        $account->refresh();

        return response()->json([
            'stamp_count' => $account->stamp_count,
            'reward_target' => $account->store->reward_target,
            'reward_available_at' => $account->reward_available_at?->toIso8601String(),
            'reward_redeemed_at' => $account->reward_redeemed_at?->toIso8601String(),
            'redeem_token' => $account->redeem_token,
            'public_token' => $account->public_token,
            'store_name' => $account->store->name,
            'reward_title' => $account->store->reward_title,
            'customer_name' => $account->customer->name ?? 'Valued Customer',
        ]);
    }

    public function transactions(string $public_token)
    {
        $account = LoyaltyAccount::where('public_token', $public_token)
            ->firstOrFail();

        // Get recent transactions (last 30 days, limit 50)
        $transactions = PointsTransaction::where('loyalty_account_id', $account->id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'points' => $transaction->points,
                    'description' => $transaction->type === 'earn' 
                        ? "Earned {$transaction->points} stamp(s)" 
                        : "Redeemed " . abs($transaction->points) . " stamp(s)",
                    'timestamp' => $transaction->created_at->toIso8601String(),
                    'formatted_date' => $transaction->created_at->format('M d, Y g:i A'),
                ];
            });

        return response()->json([
            'transactions' => $transactions,
            'total_count' => $transactions->count(),
        ]);
    }
}
