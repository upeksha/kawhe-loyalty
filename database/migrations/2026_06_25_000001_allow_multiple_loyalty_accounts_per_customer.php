<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_accounts', function (Blueprint $table) {
            $table->dropUnique(['store_id', 'customer_id']);
            $table->unique(['loyalty_program_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::table('loyalty_accounts', function (Blueprint $table) {
            $table->dropUnique(['loyalty_program_id', 'customer_id']);
            $table->unique(['store_id', 'customer_id']);
        });
    }
};
