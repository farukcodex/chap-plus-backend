<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DummyUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Generate 10 standard users with exact, predictable emails (user1@yopmail.com to user10@yopmail.com)
        for ($i = 1; $i <= 10; $i++) {
            $user = \App\Models\User::updateOrCreate(
                ['email' => 'user' . $i . '@yopmail.com'],
                ['name' => 'User ' . $i, 'password' => \Illuminate\Support\Facades\Hash::make('12348765'), 'email_verified_at' => now()]
            );
            $user->assignRole('USER');
        }
    }
}
