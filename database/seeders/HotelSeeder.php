<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MerchantProfile;
use App\Models\Hotel;

class HotelSeeder extends Seeder
{
    public function run(): void
    {
        $hotelMerchants = MerchantProfile::whereHas('user.roles', function($query) {
            $query->where('name', 'HOTEL_MERCHANT');
        })->get();

        if ($hotelMerchants->isEmpty()) {
            return;
        }

        foreach ($hotelMerchants as $merchant) {
            Hotel::factory(2)->create([
                'merchant_profile_id' => $merchant->id
            ]);
        }
    }
}
