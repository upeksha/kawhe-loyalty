<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('slug')->unique();
            $table->integer('reward_target')->nullable();
            $table->string('reward_title')->default('Free coffee');
            $table->string('join_token', 64)->unique();
            $table->string('join_short_code', 16)->nullable()->unique();
            $table->string('brand_color', 7)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('background_color', 7)->nullable();
            $table->string('pass_logo_path')->nullable();
            $table->string('pass_hero_image_path')->nullable();
            $table->boolean('require_verification_for_redemption')->default(true);
            $table->json('registration_form_config')->nullable();
            $table->unsignedInteger('sort_order')->default(1);
            $table->boolean('is_default')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->foreignId('default_loyalty_program_id')->nullable()->after('user_id')->constrained('loyalty_programs')->nullOnDelete();
        });

        Schema::table('loyalty_accounts', function (Blueprint $table) {
            $table->foreignId('loyalty_program_id')->nullable()->after('store_id')->constrained('loyalty_programs')->nullOnDelete();
        });

        $stores = DB::table('stores')->select([
            'id',
            'name',
            'slug',
            'reward_target',
            'reward_title',
            'join_token',
            'join_short_code',
            'brand_color',
            'logo_path',
            'background_color',
            'pass_logo_path',
            'pass_hero_image_path',
            'require_verification_for_redemption',
            'registration_form_config',
            'deleted_at',
            'created_at',
            'updated_at',
        ])->get();

        foreach ($stores as $store) {
            $programId = DB::table('loyalty_programs')->insertGetId([
                'store_id' => $store->id,
                'name' => $store->reward_title ?: ($store->name . ' Loyalty Card'),
                'slug' => $store->slug,
                'reward_target' => $store->reward_target,
                'reward_title' => $store->reward_title ?: 'Free coffee',
                'join_token' => $store->join_token,
                'join_short_code' => $store->join_short_code,
                'brand_color' => $store->brand_color,
                'logo_path' => $store->logo_path,
                'background_color' => $store->background_color,
                'pass_logo_path' => $store->pass_logo_path,
                'pass_hero_image_path' => $store->pass_hero_image_path,
                'require_verification_for_redemption' => $store->require_verification_for_redemption ?? true,
                'registration_form_config' => $store->registration_form_config,
                'sort_order' => 1,
                'is_default' => true,
                'deleted_at' => $store->deleted_at,
                'created_at' => $store->created_at,
                'updated_at' => $store->updated_at,
            ]);

            DB::table('stores')->where('id', $store->id)->update([
                'default_loyalty_program_id' => $programId,
            ]);

            DB::table('loyalty_accounts')->where('store_id', $store->id)->update([
                'loyalty_program_id' => $programId,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('loyalty_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('loyalty_program_id');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_loyalty_program_id');
        });

        Schema::dropIfExists('loyalty_programs');
    }
};
