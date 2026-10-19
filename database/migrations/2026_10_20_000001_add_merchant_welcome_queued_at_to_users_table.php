<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('merchant_welcome_queued_at')->nullable();
        });

        // Existing accounts already had the welcome email queued at registration.
        DB::table('users')->update(['merchant_welcome_queued_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('merchant_welcome_queued_at');
        });
    }
};
