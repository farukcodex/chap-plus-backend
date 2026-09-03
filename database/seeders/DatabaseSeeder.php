<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,   // must run first — creates roles
            AdminSeeder::class,
            MerchantRoleSeeder::class,
            ProductCategorySeeder::class,
            PlatformSettingSeeder::class,
            PageSeeder::class,

            // Uncomment the lines below to generate fake data for local testing
            DummyUserSeeder::class,
            DummyMerchantSeeder::class,
            DummyRiderSeeder::class,
            ProductSeeder::class,
            HotelSeeder::class,
            CountryDeliveryFeeSeeder::class,
            UserAddressSeeder::class,
            UserProfileSeeder::class,

        ]);
    }
}
