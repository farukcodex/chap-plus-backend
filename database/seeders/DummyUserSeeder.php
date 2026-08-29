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
        $users = \App\Models\User::factory(10)
            ->sequence(fn ($sequence) => ['email' => 'user' . ($sequence->index + 1) . '@yopmail.com'])
            ->create();
        
        foreach ($users as $user) {
            $user->assignRole('USER');
        }
    }
}
