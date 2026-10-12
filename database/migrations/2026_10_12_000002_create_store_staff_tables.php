<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('is_staff_account')->default(false));
        Schema::create('store_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('can_stamp')->default(true);
            $table->boolean('can_redeem')->default(true);
            $table->timestamps();
            $table->unique(['store_id', 'user_id']);
        });
        Schema::create('store_staff_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->string('token_hash', 64)->unique();
            $table->boolean('can_stamp');
            $table->boolean('can_redeem');
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique(['store_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_staff_invitations');
        Schema::dropIfExists('store_staff');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_staff_account'));
    }
};
