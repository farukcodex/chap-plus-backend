<?php

namespace Database\Seeders;

use App\Models\Bus;
use App\Models\BusImage;
use App\Models\MerchantProfile;
use Illuminate\Database\Seeder;

class BusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $busMerchant = MerchantProfile::whereHas('user', function ($query) {
            $query->where('email', 'bus1@yopmail.com');
        })->first();

        if (!$busMerchant) {
            $busMerchant = MerchantProfile::whereHas('user.roles', function ($query) {
                $query->where('name', 'BUS_MERCHANT');
            })->first();
        }

        if (!$busMerchant) {
            $this->command?->warn('No bus merchant profile found. Run DummyMerchantSeeder first.');
            return;
        }

        $demoBuses = [
            [
                'name' => 'Green Line Express',
                'description' => 'Luxury AC Bus',
                'price_per_seat' => 15.50,
                'bus_type' => 'AC',
                'driver_position' => 'RHD',
                'seat_pattern' => '2-3',
                'total_rows' => 10,
                'back_row_seats' => 5,
                'total_bookable_seats' => 45,
                'has_middle_door' => false,
                'departure_place' => 'Dhaka',
                'departure_time' => '06:30',
                'destination_place' => 'Chittagong',
                'destination_time' => '12:00',
                'facilities' => ['WiFi', 'Water Bottle', 'Air Conditioning'],
                'is_active' => true,
                'images' => [
                    ['path' => 'bus_images/4DpUuL0R5IqqOD4qq01AkqH4kwfQggosAAPeOptB.jpg', 'is_primary' => true],
                    ['path' => 'bus_images/7AbaQ1sd9oqF4mjg5gILl9NjydipV5D8Y45cGeiL.jpg', 'is_primary' => false],
                ],
            ],
            [
                'name' => 'Hanif Paribahan (Non-AC)',
                'description' => 'Standard Economy Bus',
                'price_per_seat' => 8.50,
                'bus_type' => 'Non-AC',
                'driver_position' => 'RHD',
                'seat_pattern' => '2-3',
                'total_rows' => 11,
                'back_row_seats' => 5,
                'total_bookable_seats' => 55,
                'has_middle_door' => false,
                'departure_place' => 'Sylhet',
                'departure_time' => '09:00',
                'destination_place' => 'Dhaka',
                'destination_time' => '14:30',
                'facilities' => ['Fan', 'Charging Port'],
                'is_active' => true,
                'images' => [
                    ['path' => 'bus_images/BpuZQbJgUZu55RTPR4GsKs03NFmGGshn4aeHAC59.jpg', 'is_primary' => true],
                ],
            ],
            [
                'name' => 'Ena Travels Sleeper',
                'description' => 'Premium Sleeper Bus with full recliners',
                'price_per_seat' => 30.00,
                'bus_type' => 'Sleeper',
                'driver_position' => 'RHD',
                'seat_pattern' => '2-1',
                'total_rows' => 8,
                'back_row_seats' => 0,
                'total_bookable_seats' => 24,
                'has_middle_door' => false,
                'departure_place' => 'Dhaka',
                'departure_time' => '22:00',
                'destination_place' => 'Coxs Bazar',
                'destination_time' => '07:00',
                'facilities' => ['Blanket', 'Water Bottle', 'Reclining Seat', 'Reading Light'],
                'is_active' => true,
                'images' => [
                    ['path' => 'bus_images/VpmtzCplNZQdkz0rcoMObylc6Sw2Cey4DmbhzP8m.jpg', 'is_primary' => true],
                ],
            ],
            [
                'name' => 'VIP Tourist Hiace',
                'description' => 'Luxury 11 Seater Microbus',
                'price_per_seat' => 20.00,
                'bus_type' => 'AC',
                'driver_position' => 'RHD',
                'seat_pattern' => '1-1',
                'total_rows' => 5,
                'back_row_seats' => 3,
                'total_bookable_seats' => 11,
                'has_middle_door' => false,
                'departure_place' => 'Dhaka',
                'departure_time' => '07:00',
                'destination_place' => 'Bandarban',
                'destination_time' => '16:00',
                'facilities' => ['WiFi', 'AC', 'Sound System'],
                'is_active' => true,
                'images' => [
                    ['path' => 'bus_images/r4xxyfpRQe3sl32GL5CsYaqbMM02ZyVqtkNyHfm4.jpg', 'is_primary' => true],
                    ['path' => 'bus_images/soTlV2chlrYa0Qt2Al60euIKhftVh5CdFc3mgEDM.jpg', 'is_primary' => false],
                ],
            ],
        ];

        foreach ($demoBuses as $data) {
            $images = $data['images'];
            unset($data['images']);

            $bus = Bus::updateOrCreate(
                [
                    'merchant_profile_id' => $busMerchant->id,
                    'name' => $data['name'],
                ],
                $data
            );

            foreach ($images as $imgData) {
                BusImage::firstOrCreate(
                    [
                        'bus_id' => $bus->id,
                        'image_path' => $imgData['path'],
                    ],
                    [
                        'is_primary' => $imgData['is_primary'],
                    ]
                );
            }
        }
    }
}
