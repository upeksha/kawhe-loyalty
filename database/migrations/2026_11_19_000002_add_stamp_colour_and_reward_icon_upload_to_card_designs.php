<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['stores', 'loyalty_programs'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('stamp_color', 7)->nullable();
                $table->string('reward_icon_path')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['stores', 'loyalty_programs'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['stamp_color', 'reward_icon_path']);
            });
        }
    }
};
