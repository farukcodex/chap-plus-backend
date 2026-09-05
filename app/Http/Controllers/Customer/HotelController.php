<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\MerchantProfile;
use App\Traits\ApiResponseTrait;
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
        $query = Hotel::with(['images', 'merchantProfile'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->where('is_active', true);

        // Optional filtering
        if ($request->filled('min_price')) {
            $query->where('price_per_night', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price_per_night', '<=', $request->max_price);
        }
        if ($request->filled('min_rooms')) {
            $query->where('room_quantity', '>=', $request->min_rooms);
        }

        // Host filter
        if ($request->filled('host_id')) {
            $query->where('merchant_profile_id', $request->host_id);
        }

        $properties = $query->latest()->paginate(15);

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
            ->whereHas('user.roles', function($q) {
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
        $host = MerchantProfile::with(['hotels' => function($q) {
                $q->where('is_active', true)->with('images');
            }])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->whereHas('user.roles', function($q) {
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
