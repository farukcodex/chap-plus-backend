<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DummyMerchantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Generate 5 predictable ecommerce merchants
        $ecommerceUsers = \App\Models\User::factory(5)
            ->sequence(fn ($sequence) => ['email' => 'merchant' . ($sequence->index + 1) . '@yopmail.com'])
            ->create();

        foreach ($ecommerceUsers as $index => $user) {
            $user->assignRole('ECOMMERCE_MERCHANT');
            \App\Models\MerchantProfile::factory()->create([
                'user_id' => $user->id,
                'business_name' => 'Test Shop ' . ($index + 1),
            ]);
        }

        // Generate 3 predictable restaurant merchants
        $restaurantUsers = \App\Models\User::factory(3)
            ->sequence(fn ($sequence) => ['email' => 'restaurant' . ($sequence->index + 1) . '@yopmail.com'])
            ->create();

        foreach ($restaurantUsers as $index => $user) {
            $user->assignRole('RESTAURANT_MERCHANT');
            \App\Models\MerchantProfile::factory()->create([
                'user_id' => $user->id,
                'business_name' => 'Test Restaurant ' . ($index + 1),
            ]);
        }
        // Generate 3 predictable hotel merchants
        $hotelUsers = \App\Models\User::factory(3)
            ->sequence(fn ($sequence) => ['email' => 'hotel' . ($sequence->index + 1) . '@yopmail.com'])
            ->create();

        foreach ($hotelUsers as $index => $user) {
            $user->assignRole('HOTEL_MERCHANT');
            \App\Models\MerchantProfile::factory()->create([
                'user_id' => $user->id,
                'business_name' => 'Test Hotel Manager ' . ($index + 1),
            ]);
        }
    }
}
