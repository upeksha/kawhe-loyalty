<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['stores', 'loyalty_programs'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->string('card_design', 32)->default('classic'));
        }
        // Preserve the explicitly approved testing cards when moving to a saved choice.
        if (config('app.env') === 'testing' && parse_url(config('app.url'), PHP_URL_HOST) === 'testing.kawhe.shop') {
            $slugs = ['kawhe-illustrated-wallet-qa', 'kawhe-illustrated-wallet-qa-6', 'kawhe-illustrated-wallet-qa-10', 'kawhe-illustrated-wallet-qa-20', 'kawhe-illustrated-wallet-qa-30'];
            \Illuminate\Support\Facades\DB::table('loyalty_programs')->whereIn('slug', $slugs)->update(['card_design' => 'illustrated_coffee']);
            $stores = \Illuminate\Support\Facades\DB::table('loyalty_programs')->whereIn('slug', $slugs)->where('is_default', true)->pluck('store_id');
            \Illuminate\Support\Facades\DB::table('stores')->whereIn('id', $stores)->update(['card_design' => 'illustrated_coffee']);
        }
    }
    public function down(): void
    {
        foreach (['stores', 'loyalty_programs'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->dropColumn('card_design'));
        }
    }
};
