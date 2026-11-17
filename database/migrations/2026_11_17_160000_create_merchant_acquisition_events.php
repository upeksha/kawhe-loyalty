<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_acquisition_events', function (Blueprint $table) {
            $table->uuid('event_id')->primary();
            $table->char('dedupe_key', 64)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('event', 32);
            $table->string('creative', 32)->nullable();
            $table->timestamp('occurred_at');
            $table->text('encrypted_click_reference')->nullable();
            $table->unsignedInteger('amount_minor')->nullable();
            $table->string('currency', 3)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->index(['user_id', 'event']);
            $table->index(['event', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_acquisition_events');
    }
};
