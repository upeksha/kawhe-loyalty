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
        // Use SQLite-compatible syntax (works with MySQL too)
        \DB::statement("
            UPDATE loyalty_accounts
            SET verified_at = (
                SELECT email_verified_at 
                FROM customers 
                WHERE customers.id = loyalty_accounts.customer_id
                AND customers.email_verified_at IS NOT NULL
            )
            WHERE customer_id IN (
                SELECT id FROM customers WHERE email_verified_at IS NOT NULL
            )
            AND verified_at IS NULL
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
