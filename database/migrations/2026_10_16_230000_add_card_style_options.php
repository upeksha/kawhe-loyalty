<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['stores', 'loyalty_programs'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('card_pattern', 32)->default('mountains');
                $table->string('stamp_icon', 32)->default('coffee');
                $table->string('stamp_icon_path')->nullable();
                $table->string('card_photo_path')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['stores', 'loyalty_programs'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['card_pattern', 'stamp_icon', 'stamp_icon_path', 'card_photo_path']));
        }
    }
};
