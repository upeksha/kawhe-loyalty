<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LoyaltyAccount>
 */
class LoyaltyAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'customer_id' => Customer::factory(),
            'stamp_count' => 0,
            'public_token' => Str::random(40),
            'version' => 1,
        ];
    }
}
