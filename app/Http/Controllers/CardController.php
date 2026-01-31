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
        try {
            $account = LoyaltyAccount::with(['store', 'customer'])
                ->where('public_token', $public_token)
                ->firstOrFail();

            // Ensure relationships are loaded
            if (!$account->store) {
                \Log::error('LoyaltyAccount has no store', ['account_id' => $account->id]);
                abort(500, 'Card configuration error. Please contact support.');
            }

            if (!$account->customer) {
                \Log::error('LoyaltyAccount has no customer', ['account_id' => $account->id]);
                abort(500, 'Card configuration error. Please contact support.');
            }

            // Ensure redeem_token exists if reward_balance > 0 (but don't regenerate if it already exists)
            $rewardBalance = $account->reward_balance ?? 0;
            if ($rewardBalance > 0) {
                try {
                    if (is_null($account->reward_available_at)) {
                        $account->reward_available_at = now();
                    }
                    // Only generate token if it doesn't exist - never regenerate existing tokens
                    if (is_null($account->redeem_token)) {
                        $account->redeem_token = Str::random(\App\Models\LoyaltyAccount::REDEEM_TOKEN_LENGTH);
                        $account->save();
                    }
                } catch (\Exception $e) {
                    \Log::warning('Error updating redeem token', [
                        'account_id' => $account->id,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue - token generation is not critical
                }
            }

            // Fix for accounts stuck in "Redeemed" state but have started a new cycle
            // Only clear reward_redeemed_at if they've started earning stamps again (old logic for backward compatibility)
            try {
                if (!is_null($account->reward_redeemed_at) && $account->stamp_count > 0 && $account->stamp_count < $account->store->reward_target && $rewardBalance == 0) {
                    $account->reward_redeemed_at = null;
                    $account->save();
                }
            } catch (\Exception $e) {
                \Log::warning('Error clearing reward_redeemed_at', [
                    'account_id' => $account->id,
                    'error' => $e->getMessage(),
                ]);
                // Continue - this is just a cleanup operation
            }

            return view('card.show', compact('account'));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            abort(404, 'Loyalty card not found.');
        } catch (\Exception $e) {
            \Log::error('Error loading card', [
                'public_token' => $public_token,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            abort(500, 'Error loading card. Please try again later.');
        }
    }

    public function api(string $public_token)
    {
        $account = LoyaltyAccount::with(['store', 'customer'])
            ->where('public_token', $public_token)
            ->firstOrFail();

        // Ensure redeem_token exists if reward_balance > 0 (but don't regenerate if it already exists)
        $rewardBalance = $account->reward_balance ?? 0;
        if ($rewardBalance > 0) {
            if (is_null($account->reward_available_at)) {
                $account->reward_available_at = now();
            }
            // Only generate token if it doesn't exist - never regenerate existing tokens
            if (is_null($account->redeem_token)) {
                $account->redeem_token = Str::random(\App\Models\LoyaltyAccount::REDEEM_TOKEN_LENGTH);
                $account->save();
            }
        }

        // Fix for accounts stuck in "Redeemed" state but have started a new cycle
        // Only clear reward_redeemed_at if they've started earning stamps again (old logic for backward compatibility)
        if (!is_null($account->reward_redeemed_at) && $account->stamp_count > 0 && $account->stamp_count < $account->store->reward_target && $rewardBalance == 0) {
            $account->reward_redeemed_at = null;
            $account->save();
        }

        // Refresh the account to get latest data
        $account->refresh();
        // Re-read balance after refresh so the API always returns the latest value (prevents stale UI after redeem)
        $rewardBalance = $account->reward_balance ?? 0;

        return response()->json([
            'stamp_count' => $account->stamp_count,
            'reward_target' => $account->store->reward_target,
            'reward_balance' => $rewardBalance,
            'reward_available' => $rewardBalance > 0,
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

    public function manifest(string $public_token)
    {
        $account = LoyaltyAccount::with(['store'])->where('public_token', $public_token)->firstOrFail();
        
        $cardUrl = route('card.show', ['public_token' => $public_token]);
        $baseUrl = rtrim(config('app.url'), '/');
        $backgroundColor = $account->store->background_color ?? '#1F2937';
        $themeColor = $account->store->brand_color ?? $backgroundColor;
        
        $manifest = [
            'name' => $account->store->name . ' - My Card',
            'short_name' => $account->store->name,
            'start_url' => $cardUrl,
            'scope' => $baseUrl . '/c/' . $public_token,
            'display' => 'standalone',
            'background_color' => $backgroundColor,
            'theme_color' => $themeColor,
            'orientation' => 'portrait-primary',
            'icons' => [
                [
                    'src' => asset('favicon.ico'),
                    'sizes' => '192x192',
                    'type' => 'image/x-icon',
                    'purpose' => 'any maskable'
                ],
                [
                    'src' => asset('favicon.ico'),
                    'sizes' => '512x512',
                    'type' => 'image/x-icon',
                    'purpose' => 'any maskable'
                ]
            ]
        ];

        return response()->json($manifest)
            ->header('Content-Type', 'application/manifest+json');
    }
}
