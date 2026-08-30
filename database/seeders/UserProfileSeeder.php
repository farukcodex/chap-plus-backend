<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = \App\Models\User::where('email', 'like', '%@yopmail.com')->get();

        foreach ($users as $user) {
            \App\Models\UserProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'country' => 'KE',
                    'city' => 'Nairobi',
                    'currency' => 'KES',
                    'phone_number' => '254712' . rand(100000, 999999),
                    'gender' => rand(0, 1) ? 'Male' : 'Female',
                    'date_of_birth' => '199' . rand(0, 9) . '-05-12',
                    'address' => 'Sample Address, Nairobi'
                ]
            );
        }
    }
}
