<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserAddressSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = \App\Models\User::where('email', 'like', '%@yopmail.com')->get();

        foreach ($users as $user) {
            \App\Models\UserAddress::updateOrCreate(
                ['user_id' => $user->id, 'title' => 'Home'],
                [
                    'address_text' => 'House 12, Road 5, Mohakhali, Dhaka',
                    'latitude' => 23.7771,
                    'longitude' => 90.3994
                ]
            );

            \App\Models\UserAddress::updateOrCreate(
                ['user_id' => $user->id, 'title' => 'Office'],
                [
                    'address_text' => '10th Floor, BRAC Tower, Dhaka',
                    'latitude' => 23.7806,
                    'longitude' => 90.4069
                ]
            );
            
            \App\Models\UserAddress::updateOrCreate(
                ['user_id' => $user->id, 'title' => 'Friend\'s House'],
                [
                    'address_text' => 'Gulshan 2, Dhaka',
                    'latitude' => 23.7925,
                    'longitude' => 90.4078
                ]
            );
        }
    }
}
