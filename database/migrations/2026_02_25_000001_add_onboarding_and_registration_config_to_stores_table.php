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
        Schema::table('stores', function (Blueprint $table) {
            $table->string('onboarding_step', 32)->nullable()->after('require_verification_for_redemption');
            $table->timestamp('onboarding_completed_at')->nullable()->after('onboarding_step');
            $table->json('registration_form_config')->nullable()->after('onboarding_completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['onboarding_step', 'onboarding_completed_at', 'registration_form_config']);
        });
    }
};
