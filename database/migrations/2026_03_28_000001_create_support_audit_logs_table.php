<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('loyalty_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type');
            $table->string('status')->default('success');
            $table->string('source')->nullable();
            $table->string('message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['event_type', 'status']);
            $table->index(['store_id', 'created_at']);
            $table->index(['loyalty_account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_audit_logs');
    }
};
