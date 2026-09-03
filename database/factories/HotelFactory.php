<?php

namespace Database\Factories;

use App\Models\MerchantProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class HotelFactory extends Factory
{
    public function definition(): array
    {
        $facilities = ['Wifi', 'Pool', 'Beach', 'AC', 'Gym', 'Spa', 'Bar', 'Restaurant'];
        // Pick 3-5 random facilities
        $randomFacilities = fake()->randomElements($facilities, fake()->numberBetween(3, 5));

        return [
            'merchant_profile_id' => MerchantProfile::factory(), // overridden by seeder usually
            'name' => fake()->company() . ' Hotel ' . fake()->word(),
            'description' => fake()->paragraph(),
            'price_per_night' => fake()->randomFloat(2, 30, 300),
            'room_quantity' => fake()->numberBetween(1, 10),
            'facilities' => $randomFacilities,
            'is_active' => true,
        ];
    }
}
