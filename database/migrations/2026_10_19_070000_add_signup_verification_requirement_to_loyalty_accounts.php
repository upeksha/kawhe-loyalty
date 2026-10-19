<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_accounts', function (Blueprint $table) {
            $table->boolean('signup_requires_verification')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('loyalty_accounts', fn (Blueprint $table) => $table->dropColumn('signup_requires_verification'));
    }
};
