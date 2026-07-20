<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_programs', function (Blueprint $table) {
            $table->unsignedBigInteger('wallet_design_version')->default(1)->after('pass_hero_image_path');
            $table->char('wallet_design_hash', 64)->nullable()->after('wallet_design_version');
            $table->json('wallet_asset_manifest')->nullable()->after('wallet_design_hash');
            $table->timestamp('wallet_assets_generated_at')->nullable()->after('wallet_asset_manifest');
            $table->timestamp('wallet_branding_updated_at')->nullable()->after('wallet_assets_generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('loyalty_programs', function (Blueprint $table) {
            $table->dropColumn([
                'wallet_design_version',
                'wallet_design_hash',
                'wallet_asset_manifest',
                'wallet_assets_generated_at',
                'wallet_branding_updated_at',
            ]);
        });
    }
};
