<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DummyRiderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $user = \App\Models\User::firstOrCreate(
                ['email' => "rider{$i}@yopmail.com"],
                [
                    'name' => "Rider {$i}",
                    'password' => \Illuminate\Support\Facades\Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            $user->assignRole('RIDER');

            $profileData = \App\Models\RiderProfile::factory()->make()->getAttributes();
            unset($profileData['user_id']); // handled by firstOrCreate condition

            \App\Models\RiderProfile::firstOrCreate(
                ['user_id' => $user->id],
                $profileData
            );
        }
    }
}
