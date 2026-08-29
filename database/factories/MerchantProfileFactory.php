<?php

namespace Database\Factories;

use App\Models\MerchantProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchantProfile>
 */
class MerchantProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory()->afterCreating(function (\App\Models\User $user) {
                $user->assignRole('ECOMMERCE_MERCHANT');
            }),
            'business_name' => fake()->company(),
            'country' => 'KE',
            'city' => 'Nairobi',
            'address' => fake()->address(),
        ];
    }
}
