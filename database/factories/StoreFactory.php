<?php

namespace Database\Factories;

use App\Models\LoyaltyProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Store>
 */
class StoreFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (\App\Models\Store $store) {
            if ($store->default_loyalty_program_id) {
                return;
            }

            $program = LoyaltyProgram::create([
                'store_id' => $store->id,
                'name' => $store->reward_title,
                'slug' => $store->slug,
                'reward_target' => $store->reward_target,
                'reward_title' => $store->reward_title,
                'join_token' => $store->join_token,
                'join_short_code' => $store->join_short_code,
                'brand_color' => $store->brand_color,
                'background_color' => $store->background_color,
                'logo_path' => $store->logo_path,
                'pass_logo_path' => $store->pass_logo_path,
                'pass_hero_image_path' => $store->pass_hero_image_path,
                'require_verification_for_redemption' => $store->require_verification_for_redemption ?? true,
                'registration_form_config' => $store->registration_form_config,
                'is_default' => true,
                'sort_order' => 1,
            ]);

            $store->forceFill(['default_loyalty_program_id' => $program->id])->save();
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->company . ' Coffee';
        return [
            'user_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(6),
            'address' => $this->faker->address,
            'reward_target' => 9,
            'reward_title' => 'Free coffee',
            'join_token' => Str::random(32),
        ];
    }
}
