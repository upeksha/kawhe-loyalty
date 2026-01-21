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
        Schema::table('stores', function (Blueprint $table) {
            // Pass logo for wallet passes (separate from store logo)
            $table->string('pass_logo_path')->nullable()->after('logo_path');
            // Hero/banner image for wallet passes
            $table->string('pass_hero_image_path')->nullable()->after('pass_logo_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['pass_logo_path', 'pass_hero_image_path']);
        });
    }
};
