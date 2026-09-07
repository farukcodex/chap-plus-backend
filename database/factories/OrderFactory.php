<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use App\Models\MerchantProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'merchant_profile_id' => MerchantProfile::factory(),
            'total_amount' => fake()->randomFloat(2, 10, 200),
            'delivery_fee' => fake()->randomFloat(2, 2, 10),
            'status' => 'pending_payment',
            'delivery_address' => fake()->address(),
            'payment_method' => 'mpesa',
            'delivery_otp' => '1234',
            'rider_id' => null,
            'customer_phone_number' => fake()->phoneNumber(),
        ];
    }
}