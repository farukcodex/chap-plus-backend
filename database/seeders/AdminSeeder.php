<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'omarfaruksparktech@gmail.com'],
            [
                'name' => 'MD OMAR FARUK',
                'password' => Hash::make('12348765'),
                'email_verified_at' => now(),
            ]
        );

        $admin->assignRole('admin');
    }
}
