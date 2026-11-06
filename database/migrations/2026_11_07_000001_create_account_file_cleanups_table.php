<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_file_cleanups', function (Blueprint $table) {
            $table->id();
            // Deliberately survives deletion of the user. No email or credentials.
            $table->unsignedBigInteger('deleted_user_id')->unique();
            $table->json('payload');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->index();
            $table->timestamp('completed_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('account_file_cleanups')->whereNull('completed_at')->exists()) {
            throw new RuntimeException('Finish pending account file cleanups before rolling back this migration.');
        }
        Schema::dropIfExists('account_file_cleanups');
    }
};
