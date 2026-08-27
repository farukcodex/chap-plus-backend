<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Seed the application's roles.
     */
    public function run(): void
    {
        $roles = [
            'ADMIN', 
            'USER', 
            'RIDER',
            'ECOMMERCE_MERCHANT',
            'RESTAURANT_MERCHANT',
            'HOTEL_MERCHANT',
            'BUS_MERCHANT'
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }
}
