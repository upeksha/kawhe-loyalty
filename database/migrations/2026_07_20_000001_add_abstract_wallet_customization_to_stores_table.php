<?php

use App\Models\Store;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('wallet_background_pattern', 32)
                ->default(Store::WALLET_BACKGROUND_PATTERN_ORGANIC)
                ->after('wallet_card_style');
            $table->string('wallet_pattern_color', 7)
                ->nullable()
                ->after('wallet_background_pattern');
            $table->string('wallet_stamp_icon_path')
                ->nullable()
                ->after('wallet_pattern_color');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn([
                'wallet_background_pattern',
                'wallet_pattern_color',
                'wallet_stamp_icon_path',
            ]);
        });
    }
};
