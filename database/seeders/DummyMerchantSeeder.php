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
        for ($i = 1; $i <= 5; $i++) {
            $user = \App\Models\User::updateOrCreate(
                ['email' => 'merchant' . $i . '@yopmail.com'],
                ['name' => 'Merchant ' . $i, 'password' => \Illuminate\Support\Facades\Hash::make('12348765'), 'email_verified_at' => now()]
            );
            $user->assignRole('ECOMMERCE_MERCHANT');
            \App\Models\MerchantProfile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'business_name' => 'Test Shop ' . $i,
                    'address' => 'Test Address',
                    'phone_number' => '+25472000000' . $i,
                    'latitude' => 23.7750 + ($i * 0.002),
                    'longitude' => 90.3950 + ($i * 0.002),
                    'country' => 'KE',
                    'city' => 'Nairobi',
                    'currency' => 'KES'
                ]
            );
        }

        // Generate 3 predictable restaurant merchants
        for ($i = 1; $i <= 3; $i++) {
            $user = \App\Models\User::updateOrCreate(
                ['email' => 'restaurant' . $i . '@yopmail.com'],
                ['name' => 'Restaurant ' . $i, 'password' => \Illuminate\Support\Facades\Hash::make('12348765'), 'email_verified_at' => now()]
            );
            $user->assignRole('RESTAURANT_MERCHANT');
            \App\Models\MerchantProfile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'business_name' => 'Test Restaurant ' . $i,
                    'address' => 'Test Address',
                    'phone_number' => '+25473000000' . $i,
                    'latitude' => 23.7850 + ($i * 0.002),
                    'longitude' => 90.4050 + ($i * 0.002),
                    'country' => 'KE',
                    'city' => 'Nairobi',
                    'currency' => 'KES'
                ]
            );
        }
        
        // Generate 3 predictable hotel merchants
        for ($i = 1; $i <= 3; $i++) {
            $user = \App\Models\User::updateOrCreate(
                ['email' => 'hotel' . $i . '@yopmail.com'],
                ['name' => 'Hotel ' . $i, 'password' => \Illuminate\Support\Facades\Hash::make('12348765'), 'email_verified_at' => now()]
            );
            $user->assignRole('HOTEL_MERCHANT');
            \App\Models\MerchantProfile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'business_name' => 'Test Hotel Manager ' . $i,
                    'address' => 'Test Address',
                    'phone_number' => '+25474000000' . $i,
                    'latitude' => 23.7900 + ($i * 0.002),
                    'longitude' => 90.4100 + ($i * 0.002),
                    'country' => 'KE',
                    'city' => 'Nairobi',
                    'currency' => 'KES'
                ]
            );
        }

        // Generate 3 predictable bus merchants
        for ($i = 1; $i <= 3; $i++) {
            $user = \App\Models\User::updateOrCreate(
                ['email' => 'bus' . $i . '@yopmail.com'],
                ['name' => 'Bus Merchant ' . $i, 'password' => \Illuminate\Support\Facades\Hash::make('12348765'), 'email_verified_at' => now()]
            );
            $user->assignRole('BUS_MERCHANT');
            \App\Models\MerchantProfile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'business_name' => 'Test Bus Operator ' . $i,
                    'address' => 'Test Address',
                    'phone_number' => '+25475000000' . $i,
                    'latitude' => 23.7950 + ($i * 0.002),
                    'longitude' => 90.4150 + ($i * 0.002),
                    'country' => 'US',
                    'city' => 'New York',
                    'currency' => 'USD'
                ]
            );
        }
    }
}
