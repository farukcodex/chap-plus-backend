<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HotelBooking;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class HotelBookingController extends Controller
{
    use ApiResponseTrait;

    /**
     * List all hotel bookings with filtering, search, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = HotelBooking::with([
            'user:id,name,email,profile_photo_path',
            'user.userProfile:id,user_id,phone_number',
            'merchantProfile:id,user_id,business_name,phone_number,address,city,country,currency',
            'merchantProfile.user:id,name,email',
            'hotel:id,merchant_profile_id,name,description,price_per_night,room_quantity,facilities,is_active',
        ]);

        // Search by guest phone, user name/email, hotel name, or receipt number
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('id', $search)
                  ->orWhere('customer_phone_number', 'like', "%{$search}%")
                  ->orWhere('mpesa_receipt_number', 'like', "%{$search}%")
                  ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                  ->orWhereHas('merchantProfile', fn ($mq) => $mq->where('business_name', 'like', "%{$search}%"))
                  ->orWhereHas('hotel', fn ($hq) => $hq->where('name', 'like', "%{$search}%"));
            });
        }

        // Filter by booking status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by check-in date
        if ($request->filled('check_in_date')) {
            $query->whereDate('check_in_date', $request->input('check_in_date'));
        }

        // Filter by check-out date
        if ($request->filled('check_out_date')) {
            $query->whereDate('check_out_date', $request->input('check_out_date'));
        }

        // Filter by booking creation date
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $perPage = max(1, (int) $request->input('per_page', 15));
        $bookings = $query->latest()->paginate($perPage);

        $bookings->through(fn (HotelBooking $booking) => $this->formatBookingListItem($booking));

        return $this->apiSuccess('Hotel bookings retrieved successfully', $bookings);
    }

    /**
     * Get detailed hotel booking information.
     */
    public function show(string $id): JsonResponse
    {
        $booking = HotelBooking::with([
            'user:id,name,email,profile_photo_path',
            'user.userProfile',
            'merchantProfile.user:id,name,email',
            'merchantProfile',
            'hotel.images',
            'refund',
        ])->find($id);

        if (!$booking) {
            return $this->apiError('Hotel booking not found', 404);
        }

        return $this->apiSuccess('Hotel booking details retrieved successfully', [
            'booking' => $this->formatBookingDetail($booking)
        ]);
    }

    /**
     * Update hotel booking status by Admin.
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:pending_payment,paid,confirmed,checked_in,checked_out,cancelled,failed',
        ]);

        $booking = HotelBooking::find($id);

        if (!$booking) {
            return $this->apiError('Hotel booking not found', 404);
        }

        $booking->update(['status' => $validated['status']]);

        return $this->apiSuccess('Hotel booking status updated successfully', [
            'booking' => [
                'id'             => $booking->id,
                'booking_number' => '#HTL-' . str_pad($booking->id, 5, '0', STR_PAD_LEFT),
                'status'         => $booking->status,
                'updated_at'     => $booking->updated_at?->toIso8601String(),
            ]
        ]);
    }

    /**
     * Format a hotel booking for table listing.
     */
    private function formatBookingListItem(HotelBooking $booking): array
    {
        $hotel = $booking->hotel;
        $currency = $booking->merchantProfile?->currency ?? 'KES';

        $checkIn = $booking->check_in_date ? Carbon::parse($booking->check_in_date) : null;
        $checkOut = $booking->check_out_date ? Carbon::parse($booking->check_out_date) : null;
        $nightsCount = ($checkIn && $checkOut) ? max(1, $checkIn->diffInDays($checkOut)) : 1;

        return [
            'id'                   => $booking->id,
            'booking_number'       => '#HTL-' . str_pad($booking->id, 5, '0', STR_PAD_LEFT),
            'guest'                => [
                'id'                => $booking->user?->id,
                'name'              => $booking->user?->name ?? 'Guest',
                'phone'             => $booking->customer_phone_number ?: ($booking->user?->userProfile?->phone_number ?? 'N/A'),
                'email'             => $booking->user?->email ?? 'N/A',
                'profile_photo_url' => $booking->user?->profile_photo_url,
            ],
            'hotel'                => $hotel ? [
                'id'              => $hotel->id,
                'name'            => $hotel->name,
                'price_per_night' => (float) $hotel->price_per_night,
            ] : null,
            'merchant'             => [
                'id'            => $booking->merchantProfile?->id,
                'name'          => $booking->merchantProfile?->user?->name ?? 'N/A',
                'email'         => $booking->merchantProfile?->user?->email,
                'business_name' => $booking->merchantProfile?->business_name ?? 'N/A',
                'phone_number'  => $booking->merchantProfile?->phone_number,
                'city'          => $booking->merchantProfile?->city,
                'country'       => $booking->merchantProfile?->country,
            ],
            'stay_period'          => [
                'check_in_date'  => $checkIn ? $checkIn->format('Y-m-d') : null,
                'check_out_date' => $checkOut ? $checkOut->format('Y-m-d') : null,
                'nights_count'   => (int) $nightsCount,
            ],
            'rooms_booked'         => (int) $booking->rooms_booked,
            'total_price'          => (float) $booking->total_price,
            'currency'             => $currency,
            'payment_method'       => 'mpesa',
            'mpesa_receipt_number' => $booking->mpesa_receipt_number,
            'status'               => $booking->status,
            'created_at'           => $booking->created_at?->toIso8601String(),
        ];
    }

    /**
     * Format a hotel booking with full details.
     */
    private function formatBookingDetail(HotelBooking $booking): array
    {
        $base = $this->formatBookingListItem($booking);

        $hotel = $booking->hotel;
        $base['hotel_details'] = $hotel ? [
            'id'              => $hotel->id,
            'name'            => $hotel->name,
            'description'     => $hotel->description,
            'price_per_night' => (float) $hotel->price_per_night,
            'room_quantity'   => (int) $hotel->room_quantity,
            'facilities'      => $hotel->facilities ?? [],
            'is_active'       => (bool) $hotel->is_active,
            'images'          => $hotel->images->map(fn ($img) => [
                'id'        => $img->id,
                'image_url' => $img->image_url,
            ]) ?? [],
        ] : null;

        $base['refund'] = $booking->refund ? [
            'id'             => $booking->refund->id,
            'amount'         => (float) $booking->refund->amount,
            'status'         => $booking->refund->status,
            'reason'         => $booking->refund->reason,
            'refund_method'  => $booking->refund->refund_method,
            'transaction_id' => $booking->refund->transaction_id,
            'created_at'     => $booking->refund->created_at?->toIso8601String(),
        ] : null;

        return $base;
    }
}
