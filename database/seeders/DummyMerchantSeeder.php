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
        // Generate 5 predictable merchants (merchant1@yopmail.com to merchant5@yopmail.com)
        $users = \App\Models\User::factory(5)
            ->sequence(fn ($sequence) => ['email' => 'merchant' . ($sequence->index + 1) . '@yopmail.com'])
            ->create();

        foreach ($users as $index => $user) {
            $user->assignRole('ECOMMERCE_MERCHANT');
            \App\Models\MerchantProfile::factory()->create([
                'user_id' => $user->id,
                'business_name' => 'Test Shop ' . ($index + 1),
            ]);
        }
    }
}
