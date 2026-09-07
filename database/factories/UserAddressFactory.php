<?php

namespace Database\Factories;

use App\Models\UserAddress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserAddressFactory extends Factory
{
    protected $model = UserAddress::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->randomElement(['Home', 'Office', 'Other']),
            'address_text' => fake()->streetAddress() . ', ' . fake()->city(),
            'phone_number' => fake()->phoneNumber(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
        ];
    }
}