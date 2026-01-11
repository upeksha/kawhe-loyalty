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
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('phone');
            $table->string('email_verification_token_hash', 64)->nullable()->after('email_verified_at');
            $table->timestamp('email_verification_expires_at')->nullable()->after('email_verification_token_hash');
            $table->timestamp('email_verification_sent_at')->nullable()->after('email_verification_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'email_verified_at',
                'email_verification_token_hash',
                'email_verification_expires_at',
                'email_verification_sent_at',
            ]);
        });
    }
};
