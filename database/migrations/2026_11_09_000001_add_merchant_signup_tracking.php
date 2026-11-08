<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('signup_attribution')->nullable();
        });
        Schema::create('merchant_signup_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event', 20);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'event']);
            $table->index(['sent_at', 'attempts']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_signup_alerts');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('signup_attribution'));
    }
};
