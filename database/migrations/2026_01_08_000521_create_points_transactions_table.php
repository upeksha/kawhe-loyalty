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
        Schema::create('points_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loyalty_account_id')->constrained()->onDelete('cascade');
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('type'); // 'earn' or 'redeem'
            $table->integer('points'); // Positive for earn, negative for redeem
            $table->string('idempotency_key')->unique(); // Prevent duplicate processing
            $table->text('metadata')->nullable(); // JSON for additional data
            $table->timestamps(); // created_at and updated_at
            
            // Indexes for performance
            $table->index('loyalty_account_id');
            $table->index('store_id');
            $table->index('created_at');
            $table->index('idempotency_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('points_transactions');
    }
};
