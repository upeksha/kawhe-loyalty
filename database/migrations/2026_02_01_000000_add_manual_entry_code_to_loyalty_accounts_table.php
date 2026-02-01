<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds a 4-character manual entry code (e.g. A3CX) unique per store for fast manual entry.
     */
    public function up(): void
    {
        Schema::table('loyalty_accounts', function (Blueprint $table) {
            $table->string('manual_entry_code', 4)->nullable()->after('redeem_token');
        });

        // Unique per store (same 4-char code can exist in different stores)
        Schema::table('loyalty_accounts', function (Blueprint $table) {
            $table->unique(['store_id', 'manual_entry_code']);
        });

        // Backfill existing rows with unique 4-char codes per store
        $accounts = DB::table('loyalty_accounts')->select('id', 'store_id')->get();
        $usedByStore = [];
        foreach ($accounts as $row) {
            $code = $this->generateUniqueCode($row->store_id, $usedByStore);
            $usedByStore[$row->store_id][] = $code;
            DB::table('loyalty_accounts')->where('id', $row->id)->update(['manual_entry_code' => $code]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loyalty_accounts', function (Blueprint $table) {
            $table->dropUnique(['store_id', 'manual_entry_code']);
            $table->dropColumn('manual_entry_code');
        });
    }

    private function generateUniqueCode(int $storeId, array &$usedByStore): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Exclude ambiguous I,O,0,1
        $max = 20;
        for ($i = 0; $i < $max; $i++) {
            $code = '';
            for ($j = 0; $j < 4; $j++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $used = $usedByStore[$storeId] ?? [];
            if (!in_array($code, $used, true)) {
                return $code;
            }
        }
        // Fallback: use Str::random if collision (very unlikely)
        return strtoupper(Str::random(4));
    }
};
