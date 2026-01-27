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
        Schema::table('loyalty_accounts', function (Blueprint $table) {
            // Add email verification fields for store-specific verification
            $table->string('email_verification_token_hash', 64)->nullable()->after('verified_at');
            $table->timestamp('email_verification_expires_at')->nullable()->after('email_verification_token_hash');
            $table->timestamp('email_verification_sent_at')->nullable()->after('email_verification_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loyalty_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'email_verification_token_hash',
                'email_verification_expires_at',
                'email_verification_sent_at',
            ]);
        });
    }
};
