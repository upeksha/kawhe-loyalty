<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('loyalty_accounts', function (Blueprint $table) {
            $table->string('redeem_token')->nullable()->unique()->after('public_token');
            $table->timestamp('reward_available_at')->nullable()->after('last_stamped_at');
            $table->timestamp('reward_redeemed_at')->nullable()->after('reward_available_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loyalty_accounts', function (Blueprint $table) {
            $table->dropColumn(['redeem_token', 'reward_available_at', 'reward_redeemed_at']);
        });
    }
};
