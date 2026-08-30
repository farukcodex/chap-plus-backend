<?php

namespace Database\Factories;

use App\Models\RiderProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RiderProfile>
 */
class RiderProfileFactory extends Factory
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
                $user->assignRole('RIDER');
            }),
            'phone_number' => '2547' . fake()->randomNumber(8, true),
            'gender' => fake()->randomElement(['Male', 'Female']),
            'dob' => fake()->dateTimeBetween('-40 years', '-18 years')->format('Y-m-d'),
            'address' => fake()->address(),
            'country' => 'KE',
            'city' => 'Nairobi',
            'currency' => 'KES',
            'mpesa_payout_number' => '2547' . fake()->randomNumber(8, true),
            'status' => 'approved',
        ];
    }
}
