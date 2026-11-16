<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->text('customer_welcome_note')->nullable();
            $table->string('customer_reply_to')->nullable();
            $table->string('pending_customer_reply_to')->nullable();
            $table->string('customer_reply_to_token_hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['customer_welcome_note', 'customer_reply_to', 'pending_customer_reply_to', 'customer_reply_to_token_hash']);
        });
    }
};
