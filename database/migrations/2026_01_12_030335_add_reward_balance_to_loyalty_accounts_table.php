<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('loyalty_accounts', function (Blueprint $table) {
            $table->integer('reward_balance')->default(0)->after('stamp_count');
        });

        // Backfill existing data: convert stamp_count to reward_balance + remainder
        // This safely migrates existing accounts without breaking current state
        $accounts = DB::table('loyalty_accounts')
            ->join('stores', 'loyalty_accounts.store_id', '=', 'stores.id')
            ->select('loyalty_accounts.id', 'loyalty_accounts.stamp_count', 'stores.reward_target')
            ->get();

        foreach ($accounts as $accountData) {
            $stampCount = $accountData->stamp_count;
            $rewardTarget = $accountData->reward_target;
            
            if ($stampCount >= $rewardTarget) {
                $newlyEarned = intval(floor($stampCount / $rewardTarget));
                $remainder = $stampCount % $rewardTarget;
                
                DB::table('loyalty_accounts')
                    ->where('id', $accountData->id)
                    ->update([
                        'reward_balance' => $newlyEarned,
                        'stamp_count' => $remainder,
                    ]);
                
                // Ensure redeem_token and reward_available_at are set if rewards available
                if ($newlyEarned > 0) {
                    $existing = DB::table('loyalty_accounts')->where('id', $accountData->id)->first();
                    $updates = [];
                    
                    if (is_null($existing->reward_available_at)) {
                        $updates['reward_available_at'] = now();
                    }
                    
                    if (is_null($existing->redeem_token)) {
                        $updates['redeem_token'] = \Illuminate\Support\Str::random(40);
                    }
                    
                    if (!empty($updates)) {
                        DB::table('loyalty_accounts')
                            ->where('id', $accountData->id)
                            ->update($updates);
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Before dropping, convert reward_balance back to stamp_count
        // This restores the old behavior for rollback
        $accounts = DB::table('loyalty_accounts')
            ->join('stores', 'loyalty_accounts.store_id', '=', 'stores.id')
            ->select('loyalty_accounts.id', 'loyalty_accounts.stamp_count', 'loyalty_accounts.reward_balance', 'stores.reward_target')
            ->get();

        foreach ($accounts as $accountData) {
            $stampCount = $accountData->stamp_count;
            $rewardBalance = $accountData->reward_balance ?? 0;
            $rewardTarget = $accountData->reward_target;
            
            $totalStamps = $stampCount + ($rewardBalance * $rewardTarget);
            
            DB::table('loyalty_accounts')
                ->where('id', $accountData->id)
                ->update(['stamp_count' => $totalStamps]);
        }

        Schema::table('loyalty_accounts', function (Blueprint $table) {
            $table->dropColumn('reward_balance');
        });
    }
};
