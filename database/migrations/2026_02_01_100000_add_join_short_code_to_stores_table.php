<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Add a short join code (e.g. 6 chars) so join URL is https://domain.com/j/abc12x
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('join_short_code', 8)->nullable()->unique()->after('join_token');
        });

        // Backfill existing stores with a unique 6-char code
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no I,O,0,1
        $stores = DB::table('stores')->whereNull('join_short_code')->get();
        $used = DB::table('stores')->whereNotNull('join_short_code')->pluck('join_short_code')->flip()->all();

        foreach ($stores as $row) {
            do {
                $code = '';
                for ($i = 0; $i < 6; $i++) {
                    $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
                }
            } while (isset($used[$code]));
            $used[$code] = true;
            DB::table('stores')->where('id', $row->id)->update(['join_short_code' => $code]);
        }
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropUnique(['join_short_code']);
            $table->dropColumn('join_short_code');
        });
    }
};
