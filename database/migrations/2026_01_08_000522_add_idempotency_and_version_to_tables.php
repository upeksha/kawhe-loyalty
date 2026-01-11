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
        // Add version column to loyalty_accounts for optimistic locking
        Schema::table('loyalty_accounts', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(0)->after('reward_redeemed_at');
        });

        // Add idempotency_key to stamp_events
        Schema::table('stamp_events', function (Blueprint $table) {
            $table->string('idempotency_key')->nullable()->unique()->after('count');
            $table->index('idempotency_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loyalty_accounts', function (Blueprint $table) {
            $table->dropColumn('version');
        });

        Schema::table('stamp_events', function (Blueprint $table) {
            $table->dropIndex(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
