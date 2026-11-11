<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->timestamp('launch_poster_downloaded_at')->nullable();
            $table->timestamp('launch_merchant_app_installed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['launch_poster_downloaded_at', 'launch_merchant_app_installed_at']);
        });
    }
};
