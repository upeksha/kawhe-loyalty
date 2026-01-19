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
        Schema::create('apple_wallet_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('device_library_identifier')->index();
            $table->string('push_token');
            $table->string('pass_type_identifier')->index();
            $table->string('serial_number')->index();
            $table->foreignId('loyalty_account_id')->nullable()->constrained('loyalty_accounts')->onDelete('cascade');
            $table->boolean('active')->default(true);
            $table->timestamp('last_registered_at')->nullable();
            $table->timestamps();

            // Unique constraint ensures idempotency
            $table->unique(['device_library_identifier', 'pass_type_identifier', 'serial_number'], 'device_pass_serial_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apple_wallet_registrations');
    }
};
