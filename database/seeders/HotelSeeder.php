<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MerchantProfile;
use App\Models\Hotel;
use App\Models\HotelImage;
use App\Models\HotelReview;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class HotelSeeder extends Seeder
{
    public function run(): void
    {
        $hotelMerchants = MerchantProfile::whereHas('user.roles', function ($query) {
            $query->where('name', 'HOTEL_MERCHANT');
        })->get();

        if ($hotelMerchants->isEmpty()) {
            return;
        }

        // 1. Standard images from Property 10
        $standardImages = [
            [
                'image_path' => 'hotel_images/MleM0GNY8EIH55LcIIqVgj7evFun45zkGbAGQq9E.jpg',
                'is_primary' => true,
            ],
            [
                'image_path' => 'hotel_images/2SRWdF11bUTHCyN5YO8ltdUxObZlfbdx2FyChXSI.jpg',
                'is_primary' => false,
            ],
        ];

        // 2. Realistic hotel reviews
        $sampleReviews = [
            [
                'rating' => 5,
                'comment' => 'Absolutely loved our stay! The ocean view from the balcony was breathtaking, and the room was spotless. The staff went above and beyond to make us feel welcome. Will definitely be returning!',
            ],
            [
                'rating' => 5,
                'comment' => 'Outstanding hospitality and serene atmosphere. The breakfast buffet had an amazing selection of fresh local and continental dishes. Fast Wi-Fi throughout the property made working remotely seamless.',
            ],
            [
                'rating' => 4,
                'comment' => 'Great location, close to everything yet very peaceful at night. The swimming pool was well-maintained and refreshing. The room was spacious with a very comfortable bed. Highly recommend!',
            ],
            [
                'rating' => 5,
                'comment' => 'A hidden gem! Beautiful modern decor, exceptionally clean rooms, and very friendly front desk staff. The private beach access and garden were incredible highlights of our trip.',
            ],
            [
                'rating' => 4,
                'comment' => 'Wonderful weekend getaway. Check-in was smooth and swift, air conditioning worked perfectly in the tropical heat, and the on-site restaurant served delicious dinners.',
            ],
            [
                'rating' => 5,
                'comment' => 'Exceptional service from check-in to check-out. The infinity pool overlooking the sunset is unforgettable. Housekeeping was impeccable each day. Easily one of the finest boutique stays in town.',
            ],
            [
                'rating' => 5,
                'comment' => 'Peaceful, scenic, and luxurious without being pretentious. The bed was like sleeping on a cloud, and the bathroom amenities were top notch. Can’t wait to come back with family!',
            ],
            [
                'rating' => 4,
                'comment' => 'Very pleasant experience. The staff was very attentive to our requests, room service was prompt, and the premises felt safe and secure. Great value for the price per night.',
            ],
            [
                'rating' => 5,
                'comment' => 'Stunning boutique hotel! The architectural design and cozy ambient lighting gave the whole place a magical vibe. Excellent food and cocktails at the lounge.',
            ],
            [
                'rating' => 5,
                'comment' => 'Everything was top tier. Cleanliness 10/10, staff friendliness 10/10, location 10/10. Definitely bookmarking this place for my next vacation.',
            ],
        ];

        // 3. Ensure we have customer users to write reviews
        $customers = User::role('USER')->get();
        if ($customers->count() < 5) {
            $demoNames = [
                'Sarah Jenkins' => 'sarah.j@example.com',
                'David Miller' => 'david.m@example.com',
                'Elena Rostova' => 'elena.r@example.com',
                'Michael Chang' => 'michael.c@example.com',
                'Amina Hassan' => 'amina.h@example.com',
            ];
            foreach ($demoNames as $name => $email) {
                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => $name,
                        'password' => Hash::make('password'),
                        'email_verified_at' => now(),
                    ]
                );
                if (!$user->hasRole('USER')) {
                    $user->assignRole('USER');
                }
            }
            $customers = User::role('USER')->get();
        }

        // 4. Ensure properties exist for each merchant
        foreach ($hotelMerchants as $merchant) {
            $existingCount = Hotel::where('merchant_profile_id', $merchant->id)->count();
            if ($existingCount === 0) {
                Hotel::factory(2)->create([
                    'merchant_profile_id' => $merchant->id,
                    'address' => $merchant->address ?? 'Beach Road, Nyali',
                    'city' => $merchant->city ?? 'Mombasa',
                    'lat' => $merchant->latitude ?? -4.0434770,
                    'lon' => $merchant->longitude ?? 39.6682060,
                ]);
            }
        }

        // 5. Update ALL hotels with the standard images and real reviews
        $allHotels = Hotel::all();

        foreach ($allHotels as $hotel) {
            // A. Attach the standard images (from Property 10)
            HotelImage::where('hotel_id', $hotel->id)->delete();
            foreach ($standardImages as $img) {
                HotelImage::create([
                    'hotel_id' => $hotel->id,
                    'image_path' => $img['image_path'],
                    'is_primary' => $img['is_primary'],
                ]);
            }

            // B. Attach real reviews (2 to 4 per hotel)
            HotelReview::where('hotel_id', $hotel->id)->delete();
            $reviewCount = rand(2, 4);
            $selectedUsers = $customers->shuffle()->take(min($customers->count(), $reviewCount));
            $shuffledReviews = collect($sampleReviews)->shuffle();
            $i = 0;

            foreach ($selectedUsers as $reviewer) {
                $reviewData = $shuffledReviews[$i % count($sampleReviews)];
                HotelReview::create([
                    'hotel_id' => $hotel->id,
                    'user_id' => $reviewer->id,
                    'rating' => $reviewData['rating'],
                    'comment' => $reviewData['comment'],
                ]);
                $i++;
            }
        }
    }
}
