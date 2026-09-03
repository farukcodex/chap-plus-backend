<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\HotelBooking;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HotelBookingController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $merchantProfile = $request->user()->merchantProfile;

        $query = HotelBooking::with(['hotel', 'user.profile'])
            ->where('merchant_profile_id', $merchantProfile->id);

        if ($request->filled('status')) {
            $statuses = explode(',', $request->status);
            $query->whereIn('status', $statuses);
        }

        $bookings = $query->latest()->paginate(15);

        return $this->apiSuccess('Bookings retrieved successfully', ['bookings' => $bookings]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $merchantProfile = $request->user()->merchantProfile;

        $booking = HotelBooking::with(['hotel', 'user.profile'])
            ->where('merchant_profile_id', $merchantProfile->id)
            ->find($id);

        if (!$booking) {
            return $this->apiError('Booking not found', 404);
        }

        return $this->apiSuccess('Booking details retrieved', ['booking' => $booking]);
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $merchantProfile = $request->user()->merchantProfile;

        $validated = $request->validate([
            'status' => 'required|in:checked_in,checked_out,cancelled'
        ]);

        $booking = HotelBooking::where('merchant_profile_id', $merchantProfile->id)->find($id);

        if (!$booking) {
            return $this->apiError('Booking not found', 404);
        }

        // Basic state machine validation
        if ($booking->status === 'pending_payment') {
            return $this->apiError('Cannot update an unpaid booking', 400);
        }
        
        if ($booking->status === 'cancelled') {
            return $this->apiError('Cannot update a cancelled booking', 400);
        }

        $booking->update(['status' => $validated['status']]);

        return $this->apiSuccess('Booking status updated to ' . $validated['status'], ['booking' => $booking]);
    }
}
