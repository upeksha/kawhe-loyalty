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
        // Add logging columns to stamp_events
        Schema::table('stamp_events', function (Blueprint $table) {
            $table->string('user_agent')->nullable()->after('idempotency_key');
            $table->string('ip_address', 45)->nullable()->after('user_agent'); // IPv6 support
            $table->index('ip_address');
        });

        // Add logging columns to points_transactions
        Schema::table('points_transactions', function (Blueprint $table) {
            $table->string('user_agent')->nullable()->after('metadata');
            $table->string('ip_address', 45)->nullable()->after('user_agent'); // IPv6 support
            $table->index('ip_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stamp_events', function (Blueprint $table) {
            $table->dropIndex(['ip_address']);
            $table->dropColumn(['user_agent', 'ip_address']);
        });

        Schema::table('points_transactions', function (Blueprint $table) {
            $table->dropIndex(['ip_address']);
            $table->dropColumn(['user_agent', 'ip_address']);
        });
    }
};
