<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('loyalty_accounts', function (Blueprint $table) {
            // Add nullable column first
            $table->string('wallet_auth_token', 40)->nullable()->after('public_token');
        });

        // Backfill existing records with secure random tokens
        DB::table('loyalty_accounts')->whereNull('wallet_auth_token')->chunkById(100, function ($accounts) {
            foreach ($accounts as $account) {
                DB::table('loyalty_accounts')
                    ->where('id', $account->id)
                    ->update(['wallet_auth_token' => Str::random(40)]);
            }
        });

        // Now make it NOT NULL and add unique index
        Schema::table('loyalty_accounts', function (Blueprint $table) {
            $table->string('wallet_auth_token', 40)->nullable(false)->change();
            $table->unique('wallet_auth_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loyalty_accounts', function (Blueprint $table) {
            $table->dropUnique(['wallet_auth_token']);
            $table->dropColumn('wallet_auth_token');
        });
    }
};
