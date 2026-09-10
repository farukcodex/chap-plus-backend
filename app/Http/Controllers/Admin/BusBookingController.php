<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusBooking;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusBookingController extends Controller
{
    use ApiResponseTrait;

    /**
     * List all bus bookings with filtering, search, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = BusBooking::with([
            'user:id,name,email,profile_photo_path',
            'user.userProfile:id,user_id,phone_number',
            'merchantProfile:id,user_id,business_name,phone_number,address,city,country,currency',
            'merchantProfile.user:id,name,email',
            'bus:id,merchant_profile_id,name,bus_type,total_bookable_seats,departure_place,destination_place,departure_time,destination_time,price_per_seat',
        ]);

        // Search by passenger name/phone/email, operator name, or bus route
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('id', $search)
                  ->orWhere('passenger_name', 'like', "%{$search}%")
                  ->orWhere('passenger_phone', 'like', "%{$search}%")
                  ->orWhere('passenger_email', 'like', "%{$search}%")
                  ->orWhere('mpesa_receipt_number', 'like', "%{$search}%")
                  ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                  ->orWhereHas('merchantProfile', fn ($mq) => $mq->where('business_name', 'like', "%{$search}%"))
                  ->orWhereHas('bus', fn ($bq) => $bq->where('name', 'like', "%{$search}%")
                      ->orWhere('departure_place', 'like', "%{$search}%")
                      ->orWhere('destination_place', 'like', "%{$search}%")
                  );
            });
        }

        // Filter by booking status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by travel date
        if ($request->filled('travel_date')) {
            $query->whereDate('travel_date', $request->input('travel_date'));
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

        $bookings->through(fn (BusBooking $booking) => $this->formatBookingListItem($booking));

        return $this->apiSuccess('Bus bookings retrieved successfully', $bookings);
    }

    /**
     * Get detailed bus booking information.
     */
    public function show(string $id): JsonResponse
    {
        $booking = BusBooking::with([
            'user:id,name,email,profile_photo_path',
            'user.userProfile',
            'merchantProfile.user:id,name,email',
            'merchantProfile',
            'bus.images',
            'refund',
        ])->find($id);

        if (!$booking) {
            return $this->apiError('Bus booking not found', 404);
        }

        return $this->apiSuccess('Bus booking details retrieved successfully', [
            'booking' => $this->formatBookingDetail($booking)
        ]);
    }

    /**
     * Update bus booking status by Admin.
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:pending_payment,paid,cancelled,failed',
        ]);

        $booking = BusBooking::find($id);

        if (!$booking) {
            return $this->apiError('Bus booking not found', 404);
        }

        $booking->update(['status' => $validated['status']]);

        return $this->apiSuccess('Bus booking status updated successfully', [
            'booking' => [
                'id'             => $booking->id,
                'booking_number' => '#BUS-' . str_pad($booking->id, 5, '0', STR_PAD_LEFT),
                'status'         => $booking->status,
                'updated_at'     => $booking->updated_at?->toIso8601String(),
            ]
        ]);
    }

    /**
     * Format a bus booking for table listing.
     */
    private function formatBookingListItem(BusBooking $booking): array
    {
        $seats = is_array($booking->seat_numbers) ? $booking->seat_numbers : json_decode($booking->seat_numbers ?? '[]', true);
        $bus = $booking->bus;
        $currency = $booking->merchantProfile?->currency ?? 'KES';

        return [
            'id'                   => $booking->id,
            'booking_number'       => '#BUS-' . str_pad($booking->id, 5, '0', STR_PAD_LEFT),
            'passenger'            => [
                'id'                => $booking->user?->id,
                'name'              => $booking->passenger_name ?: ($booking->user?->name ?? 'N/A'),
                'phone'             => $booking->passenger_phone ?: ($booking->user?->userProfile?->phone_number ?? 'N/A'),
                'email'             => $booking->passenger_email ?: ($booking->user?->email ?? 'N/A'),
                'profile_photo_url' => $booking->user?->profile_photo_url,
            ],
            'bus_operator'         => [
                'id'            => $booking->merchantProfile?->id,
                'name'          => $booking->merchantProfile?->user?->name ?? 'N/A',
                'email'         => $booking->merchantProfile?->user?->email,
                'business_name' => $booking->merchantProfile?->business_name ?? 'N/A',
                'phone_number'  => $booking->merchantProfile?->phone_number,
                'city'          => $booking->merchantProfile?->city,
                'country'       => $booking->merchantProfile?->country,
            ],
            'bus'                  => $bus ? [
                'id'                   => $bus->id,
                'name'                 => $bus->name,
                'bus_type'             => $bus->bus_type,
                'total_bookable_seats' => $bus->total_bookable_seats,
                'route'                => "{$bus->departure_place} → {$bus->destination_place}",
                'departure_place'      => $bus->departure_place,
                'destination_place'    => $bus->destination_place,
                'departure_time'       => $bus->departure_time,
                'destination_time'     => $bus->destination_time,
                'journey_duration'     => $bus->journey_duration,
            ] : null,
            'travel_date'          => $booking->travel_date ? $booking->travel_date->format('Y-m-d') : null,
            'seat_numbers'         => $seats ?: [],
            'seats_count'          => count($seats ?: []),
            'total_price'          => (float) $booking->total_price,
            'currency'             => $currency,
            'payment_method'       => $booking->payment_method,
            'mpesa_receipt_number' => $booking->mpesa_receipt_number,
            'status'               => $booking->status,
            'created_at'           => $booking->created_at?->toIso8601String(),
        ];
    }

    /**
     * Format a bus booking with full details.
     */
    private function formatBookingDetail(BusBooking $booking): array
    {
        $base = $this->formatBookingListItem($booking);

        $base['locked_until'] = $booking->locked_until?->toIso8601String();
        $base['bus_images'] = $booking->bus?->images->map(fn ($img) => [
            'id'        => $img->id,
            'image_url' => $img->image_url,
        ]) ?? [];
        $base['facilities'] = $booking->bus?->facilities ?? [];
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
