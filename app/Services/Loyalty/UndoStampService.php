<?php

namespace App\Services\Loyalty;

use App\Events\StampUpdated;
use App\Jobs\UpdateWalletPassJob;
use App\Models\LoyaltyAccount;
use App\Models\PointsTransaction;
use App\Models\StampEvent;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UndoStampService
{
    public const WINDOW_SECONDS = 60;

    public function undo(User $staff, int $storeId, int $eventId, ?string $userAgent, ?string $ipAddress): array
    {
        return DB::transaction(function () use ($staff, $storeId, $eventId, $userAgent, $ipAddress) {
            $store = Store::find($storeId);
            abort_unless($store && $staff->canOperateStore($store, 'stamp', lock: true), 403);

            $event = StampEvent::whereKey($eventId)->where('store_id', $storeId)
                ->where('type', 'stamp')->where('user_id', $staff->id)->firstOrFail();

            $account = LoyaltyAccount::whereKey($event->loyalty_account_id)
                ->where('store_id', $storeId)->lockForUpdate()->firstOrFail();

            // The account lock serializes undo with both stamping and redemption.
            $undoKey = 'stamp-undo:'.$event->id;
            $reversal = PointsTransaction::where('idempotency_key', $undoKey)->lockForUpdate()->first();
            if ($reversal && $reversal->type === 'stamp_undo' && ($reversal->metadata['stamp_event_id'] ?? null) === $event->id) {
                return $this->result($account);
            }

            abort_if($event->created_at->copy()->addSeconds(self::WINDOW_SECONDS)->lte(now()), 409, 'The 60-second undo window has ended.');
            $original = PointsTransaction::where('idempotency_key', $event->idempotency_key)
                ->where('loyalty_account_id', $account->id)->where('type', 'earn')->first();
            $before = $original?->metadata ?? [];

            abort_unless(
                array_key_exists('last_stamped_at_before', $before)
                && array_key_exists('reward_available_at_before', $before)
                && $account->version === ($before['version_after'] ?? null)
                && (int) $account->stamp_count === ($before['stamp_count_after'] ?? null)
                && $account->reward_balance === ($before['reward_balance_after'] ?? null),
                409,
                'This card has changed since the stamp was added. Undo is unavailable.',
            );

            $account->stamp_count = $before['stamp_count_before'];
            $account->reward_balance = $before['reward_balance_before'];
            $account->last_stamped_at = $before['last_stamped_at_before'];
            $account->reward_available_at = $before['reward_available_at_before'];
            $account->redeem_token = $account->reward_balance > 0
                ? ($account->redeem_token ?? Str::random(LoyaltyAccount::REDEEM_TOKEN_LENGTH))
                : null;
            $account->version++;
            $account->save();

            $audit = [
                'loyalty_account_id' => $account->id,
                'store_id' => $storeId,
                'user_id' => $staff->id,
                'type' => 'stamp_undo',
                'idempotency_key' => $undoKey,
                'user_agent' => $userAgent,
                'ip_address' => $ipAddress,
            ];
            StampEvent::create($audit + ['count' => $event->count]);
            PointsTransaction::create($audit + [
                'points' => -$event->count,
                'metadata' => [
                    'stamp_event_id' => $event->id,
                    'rewards_reversed' => $before['newly_earned_rewards'],
                    'version_before' => $before['version_after'],
                    'version_after' => $account->version,
                ],
            ]);

            DB::afterCommit(function () use ($account, $event) {
                try {
                    UpdateWalletPassJob::dispatch($account->id, $event->id)->onQueue('default');
                } catch (\Exception $exception) {
                    report($exception);
                }
                try {
                    StampUpdated::dispatch($account);
                } catch (\Exception $exception) {
                    report($exception);
                }
            });

            return $this->result($account);
        }, 5);
    }

    private function result(LoyaltyAccount $account): array
    {
        return [
            'status' => 'success',
            'success' => true,
            'stampCount' => $account->stamp_count,
            'rewardBalance' => $account->reward_balance,
        ];
    }
}
