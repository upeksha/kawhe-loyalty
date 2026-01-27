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
        // Migrate existing customer-level verification to all their loyalty accounts
        // This ensures existing verified customers don't break when we switch to store-specific verification
        \DB::statement("
            UPDATE loyalty_accounts la
            INNER JOIN customers c ON la.customer_id = c.id
            SET la.verified_at = c.email_verified_at
            WHERE c.email_verified_at IS NOT NULL 
            AND la.verified_at IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot reverse this migration safely - data would be lost
        // This is intentional as we're moving from customer-level to account-level verification
    }
};
