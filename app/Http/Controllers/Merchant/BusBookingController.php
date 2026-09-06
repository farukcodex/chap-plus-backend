<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\BusBooking;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BusBookingController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $merchantProfile = $request->user()->merchantProfile;

        $bookings = BusBooking::with(['user.userProfile', 'bus'])
            ->where('merchant_profile_id', $merchantProfile->id)
            ->latest()
            ->paginate(15);

        return $this->apiSuccess('Bus bookings retrieved successfully', [
            'bookings' => $bookings
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $merchantProfile = $request->user()->merchantProfile;

        $booking = BusBooking::with(['user.userProfile', 'bus'])
            ->where('merchant_profile_id', $merchantProfile->id)
            ->find($id);

        if (!$booking) {
            return $this->apiError('Bus booking not found', 404);
        }

        return $this->apiSuccess('Bus booking details retrieved successfully', [
            'booking' => $booking
        ]);
    }
}
