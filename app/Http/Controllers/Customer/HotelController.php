<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\MerchantProfile;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HotelController extends Controller
{
    use ApiResponseTrait;

    /**
     * List all available properties (Hotels)
     */
    public function index(Request $request): JsonResponse
    {
        // Resolve user coordinates (from query or authenticated user profile)
        $user = $request->user();
        $profileLat = $user?->userProfile?->latitude;
        $profileLon = $user?->userProfile?->longitude;

        $validated = $request->validate([
            'min_price'      => 'nullable|numeric|min:0',
            'max_price'      => ['nullable', 'numeric', 'min:0', function ($attribute, $value, $fail) use ($request) {
                if ($request->filled('min_price') && (float) $value < (float) $request->input('min_price')) {
                    $fail('The max price must be greater than or equal to the min price.');
                }
            }],
            'min_rooms'      => 'nullable|integer|min:1',
            'host_id'        => 'nullable|integer|exists:merchant_profiles,id',
            'city'           => 'nullable|string|max:255',
            'location'       => 'nullable|string|max:255',
            'destination'    => 'nullable|string|max:255',
            'check_in_date'  => 'nullable|date|after_or_equal:today',
            'check_out_date' => 'nullable|date|after:check_in_date',
            'date'           => 'nullable|date|after_or_equal:today',
            'guests'         => 'nullable|integer|min:1|max:50',
            'rooms'          => 'nullable|integer|min:1|max:50',
            'lat'            => 'nullable|numeric|between:-90,90',
            'lon'            => 'nullable|numeric|between:-180,180',
            'radius'         => 'nullable|numeric|min:0',
            'sort_by'        => [
                'nullable',
                'string',
                'in:popular,top_rated,price_low,price_high,price_asc,price_desc,latest,near,nearby',
                function ($attribute, $value, $fail) use ($request, $profileLat, $profileLon) {
                    if (in_array($value, ['near', 'nearby'], true)) {
                        $hasQueryCoords = $request->filled('lat') && $request->filled('lon');
                        $hasProfileCoords = $profileLat !== null && $profileLon !== null;
                        if (!$hasQueryCoords && !$hasProfileCoords) {
                            $fail('Latitude and longitude (lat, lon) are required when sorting by near.');
                        }
                    }
                }
            ],
            'popular'        => ['nullable', function ($attribute, $value, $fail) {
                if (!is_bool($value) && !in_array(strtolower((string) $value), ['true', 'false', '1', '0', 'yes', 'no'], true)) {
                    $fail('The popular field must be true or false.');
                }
            }],
            'per_page'       => 'nullable|integer|min:1|max:100',
            'page'           => 'nullable|integer|min:1',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 15);
        $sortBy = $validated['sort_by'] ?? null;
        $isNear = in_array($sortBy, ['near', 'nearby'], true);

        // Determine user lat & lon for near search
        $targetLat = isset($validated['lat']) ? (float) $validated['lat'] : ($profileLat !== null ? (float) $profileLat : null);
        $targetLon = isset($validated['lon']) ? (float) $validated['lon'] : ($profileLon !== null ? (float) $profileLon : null);

        $query = Hotel::query()
            ->with(['images', 'merchantProfile'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->where('hotels.is_active', true);

        if ($targetLat !== null && $targetLon !== null) {
            $query->join('merchant_profiles', 'hotels.merchant_profile_id', '=', 'merchant_profiles.id')
                ->selectRaw(
                    "(6371 * acos(
                        LEAST(1.0, GREATEST(-1.0, 
                            cos(radians(?)) * cos(radians(COALESCE(hotels.lat, merchant_profiles.latitude))) 
                            * cos(radians(COALESCE(hotels.lon, merchant_profiles.longitude)) - radians(?)) 
                            + sin(radians(?)) * sin(radians(COALESCE(hotels.lat, merchant_profiles.latitude)))
                        ))
                    )) AS distance_km",
                    [$targetLat, $targetLon, $targetLat]
                );

            if (isset($validated['radius'])) {
                $query->having('distance_km', '<=', (float) $validated['radius']);
            }
        }

        // Optional filtering
        if (isset($validated['min_price'])) {
            $query->where('hotels.price_per_night', '>=', $validated['min_price']);
        }
        if (isset($validated['max_price'])) {
            $query->where('hotels.price_per_night', '<=', $validated['max_price']);
        }
        if (isset($validated['min_rooms'])) {
            $query->where('hotels.room_quantity', '>=', $validated['min_rooms']);
        }

        // Host filter
        if (isset($validated['host_id'])) {
            $query->where('hotels.merchant_profile_id', $validated['host_id']);
        }

        // City filter
        if (!empty($validated['city'])) {
            $city = trim($validated['city']);
            $query->where(function ($q) use ($city) {
                $q->where('hotels.city', 'like', "%{$city}%")
                  ->orWhereHas('merchantProfile', function ($mq) use ($city) {
                      $mq->where('city', 'like', "%{$city}%");
                  });
            });
        }

        // Location / Destination search ("Find Your Dream Stay" - Location)
        $locationKeyword = $validated['location'] ?? $validated['destination'] ?? null;
        if (!empty($locationKeyword)) {
            $searchTerm = trim($locationKeyword);
            $query->where(function ($q) use ($searchTerm) {
                $q->where('hotels.name', 'like', "%{$searchTerm}%")
                  ->orWhere('hotels.city', 'like', "%{$searchTerm}%")
                  ->orWhere('hotels.address', 'like', "%{$searchTerm}%")
                  ->orWhereHas('merchantProfile', function ($mq) use ($searchTerm) {
                      $mq->where('business_name', 'like', "%{$searchTerm}%")
                         ->orWhere('city', 'like', "%{$searchTerm}%")
                         ->orWhere('address', 'like', "%{$searchTerm}%");
                  });
            });
        }

        // Resolve dates ("Find Your Dream Stay" - Date)
        $checkInDate = $validated['check_in_date'] ?? null;
        $checkOutDate = $validated['check_out_date'] ?? null;
        if (!$checkInDate && !empty($validated['date'])) {
            $checkInDate = Carbon::parse($validated['date'])->toDateString();
            $checkOutDate = Carbon::parse($validated['date'])->addDay()->toDateString();
        }

        // Guests & Rooms capacity calculation ("Find Your Dream Stay" - Guest)
        $guests = isset($validated['guests']) ? (int) $validated['guests'] : null;
        $requestedRooms = isset($validated['rooms']) ? (int) $validated['rooms'] : 1;

        if ($guests !== null) {
            // Overall maximum hotel capacity must accommodate guests
            // (room_quantity * max_guests >= guests)
            $query->whereRaw('(hotels.room_quantity * hotels.max_guests) >= ?', [$guests]);
        }

        // Live room availability check if dates are specified or rooms requested
        if ($checkInDate && $checkOutDate) {
            $lockCutoff = Carbon::now()->subMinutes(15);

            if ($guests !== null) {
                // If guests provided, required rooms = CEIL(guests / max_guests), at least requestedRooms
                // hotels.room_quantity - booked_rooms >= CEIL(guests / hotels.max_guests)
                $query->whereRaw(
                    "(hotels.room_quantity - COALESCE((
                        SELECT SUM(rooms_booked) 
                        FROM hotel_bookings 
                        WHERE hotel_bookings.hotel_id = hotels.id
                          AND (
                              hotel_bookings.status IN ('paid', 'checked_in')
                              OR (hotel_bookings.status = 'pending_payment' AND hotel_bookings.created_at >= ?)
                          )
                          AND hotel_bookings.check_in_date < ?
                          AND hotel_bookings.check_out_date > ?
                    ), 0)) >= GREATEST(?, CEIL(? / hotels.max_guests))",
                    [$lockCutoff, $checkOutDate, $checkInDate, $requestedRooms, $guests]
                );
            } else {
                // Check against requestedRooms
                $query->whereRaw(
                    "(hotels.room_quantity - COALESCE((
                        SELECT SUM(rooms_booked) 
                        FROM hotel_bookings 
                        WHERE hotel_bookings.hotel_id = hotels.id
                          AND (
                              hotel_bookings.status IN ('paid', 'checked_in')
                              OR (hotel_bookings.status = 'pending_payment' AND hotel_bookings.created_at >= ?)
                          )
                          AND hotel_bookings.check_in_date < ?
                          AND hotel_bookings.check_out_date > ?
                    ), 0)) >= ?",
                    [$lockCutoff, $checkOutDate, $checkInDate, $requestedRooms]
                );
            }
        } elseif (isset($validated['rooms'])) {
            $query->where('hotels.room_quantity', '>=', $requestedRooms);
        }

        // Sorting & Popular Hotels
        $isPopular = $request->boolean('popular') || $sortBy === 'popular' || $sortBy === 'top_rated';

        if ($isNear) {
            $query->orderByRaw('COALESCE(hotels.lat, merchant_profiles.latitude) IS NULL ASC')
                  ->orderBy('distance_km', 'asc')
                  ->latest('hotels.id');
        } elseif ($isPopular) {
            $query->orderByRaw('COALESCE(reviews_avg_rating, 0) DESC')
                  ->orderByDesc('reviews_count')
                  ->latest('hotels.id');
        } elseif ($sortBy === 'price_low' || $sortBy === 'price_asc') {
            $query->orderBy('hotels.price_per_night', 'asc')->latest('hotels.id');
        } elseif ($sortBy === 'price_high' || $sortBy === 'price_desc') {
            $query->orderBy('hotels.price_per_night', 'desc')->latest('hotels.id');
        } else {
            $query->latest('hotels.id');
        }

        $properties = $query->paginate($perPage);

        return $this->apiSuccess('Properties retrieved successfully', [
            'properties' => $properties
        ]);
    }

    /**
     * View details of a specific property
     */
    public function show(string $id): JsonResponse
    {
        $property = Hotel::with(['images', 'merchantProfile', 'reviews.user'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->where('is_active', true)
            ->find($id);

        if (!$property) {
            return $this->apiError('Property not found', 404);
        }

        return $this->apiSuccess('Property details retrieved', [
            'property' => $property
        ]);
    }

    /**
     * List all Hotel Hosts (Owners/Buildings)
     */
    public function hosts(Request $request): JsonResponse
    {
        $query = MerchantProfile::withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->whereHas('user.roles', function ($q) {
                $q->where('name', 'HOTEL_MERCHANT');
            });

        $hosts = $query->latest()->paginate(15);

        return $this->apiSuccess('Hosts retrieved successfully', [
            'hosts' => $hosts
        ]);
    }

    /**
     * View details of a specific Host
     */
    public function hostDetails(string $id): JsonResponse
    {
        $host = MerchantProfile::with(['hotels' => function ($q) {
            $q->where('is_active', true)->with('images');
        }])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->whereHas('user.roles', function ($q) {
                $q->where('name', 'HOTEL_MERCHANT');
            })
            ->find($id);

        if (!$host) {
            return $this->apiError('Host not found', 404);
        }

        return $this->apiSuccess('Host details retrieved', [
            'host' => $host
        ]);
    }

    /**
     * Submit a review for a property
     */
    public function addReview(Request $request, string $id): JsonResponse
    {
        $hotel = Hotel::where('is_active', true)->find($id);

        if (!$hotel) {
            return $this->apiError('Property not found', 404);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000'
        ]);

        // Verify the user actually stayed at this hotel
        $hasStayed = \App\Models\HotelBooking::where('user_id', $request->user()->id)
            ->where('hotel_id', $hotel->id)
            ->whereIn('status', ['checked_in', 'checked_out'])
            ->exists();

        if (!$hasStayed) {
            return $this->apiError('You can only review properties you have stayed at.', 403);
        }

        $review = \App\Models\HotelReview::updateOrCreate(
            ['hotel_id' => $hotel->id, 'user_id' => $request->user()->id],
            ['rating' => $validated['rating'], 'comment' => $validated['comment'] ?? null]
        );

        return $this->apiSuccess('Review submitted successfully', ['review' => $review], 201);
    }
}
