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
            $store->ensureDefaultProgramExists();
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
