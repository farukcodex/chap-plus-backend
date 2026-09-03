<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\HotelBooking;
use App\Services\MpesaService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;

class HotelBookingController extends Controller
{
    use ApiResponseTrait;

    protected $mpesaService;

    public function __construct(MpesaService $mpesaService)
    {
        $this->mpesaService = $mpesaService;
    }

    public function book(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hotel_id' => 'required|exists:hotels,id',
            'check_in_date' => 'required|date|after_or_equal:today',
            'check_out_date' => 'required|date|after:check_in_date',
            'rooms_booked' => 'required|integer|min:1',
            'phone_number' => 'required|string',
        ]);

        $hotel = Hotel::where('is_active', true)->find($validated['hotel_id']);
        
        if (!$hotel) {
            return $this->apiError('Hotel is currently unavailable', 400);
        }

        // Check TRUE availability using the Overlap & Soft-Lock Engine
        $availableRooms = $hotel->getAvailableRooms($validated['check_in_date'], $validated['check_out_date']);

        if ($validated['rooms_booked'] > $availableRooms) {
            return $this->apiError("Not enough rooms available. Only {$availableRooms} room(s) left on these dates.", 400);
        }

        $checkIn = Carbon::parse($validated['check_in_date']);
        $checkOut = Carbon::parse($validated['check_out_date']);
        $nights = $checkIn->diffInDays($checkOut);
        
        $totalPrice = $nights * $validated['rooms_booked'] * $hotel->price_per_night;

        try {
            DB::beginTransaction();

            $booking = HotelBooking::create([
                'user_id' => $request->user()->id,
                'hotel_id' => $hotel->id,
                'merchant_profile_id' => $hotel->merchant_profile_id,
                'check_in_date' => $validated['check_in_date'],
                'check_out_date' => $validated['check_out_date'],
                'rooms_booked' => $validated['rooms_booked'],
                'total_price' => $totalPrice,
                'customer_phone_number' => $validated['phone_number'],
                'status' => 'pending_payment',
            ]);

            DB::commit();

            // Initiate M-Pesa STK Push. Use HB prefix to distinguish from Orders
            $mpesaResponse = $this->mpesaService->initiateStkPush(
                $validated['phone_number'],
                $totalPrice,
                'HB-' . $booking->id,
                'Hotel Booking for ' . $hotel->name
            );

            $booking->update([
                'mpesa_checkout_request_id' => $mpesaResponse['CheckoutRequestID']
            ]);

            return $this->apiSuccess('Booking placed! Please enter your M-Pesa PIN to complete payment.', [
                'booking_id' => $booking->id,
                'total_price' => $totalPrice,
                'nights' => $nights,
                'mpesa_response' => $mpesaResponse
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return $this->apiError('Failed to process booking', 500, ['error' => $e->getMessage()]);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $bookings = HotelBooking::with(['hotel.images'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return $this->apiSuccess('Bookings retrieved successfully', ['bookings' => $bookings]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $booking = HotelBooking::with(['hotel.images', 'merchantProfile'])
            ->where('user_id', $request->user()->id)
            ->find($id);

        if (!$booking) {
            return $this->apiError('Booking not found', 404);
        }

        return $this->apiSuccess('Booking details retrieved', ['booking' => $booking]);
    }

    public function retryPayment(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => 'required|string',
        ]);

        $booking = HotelBooking::with('hotel')->where('user_id', $request->user()->id)->find($id);

        if (!$booking) {
            return $this->apiError('Booking not found', 404);
        }

        if ($booking->status === 'paid') {
            return $this->apiError('This booking is already paid.', 400);
        }

        // Re-check availability in case their soft-lock expired and someone else took the room
        $isSoftLocked = $booking->created_at->gte(now()->subMinutes(15)) && $booking->status === 'pending_payment';
        if (!$isSoftLocked) {
            $availableRooms = $booking->hotel->getAvailableRooms(
                $booking->check_in_date->format('Y-m-d'),
                $booking->check_out_date->format('Y-m-d')
            );
            
            if ($booking->rooms_booked > $availableRooms) {
                $booking->update(['status' => 'cancelled']);
                return $this->apiError("Sorry, this room was booked by someone else while your payment was pending. Please create a new booking.", 400);
            }
        }

        try {
            // By updating updated_at (via update), we implicitly refresh their soft-lock if we wanted to logic it that way.
            // But we rely on created_at for soft-lock to prevent infinite lock extensions.
            $booking->update([
                'customer_phone_number' => $validated['phone_number'],
                'status' => 'pending_payment'
            ]);

            $mpesaResponse = $this->mpesaService->initiateStkPush(
                $validated['phone_number'],
                $booking->total_price,
                'HB-' . $booking->id,
                'Hotel Booking for ' . $booking->hotel->name
            );

            $booking->update([
                'mpesa_checkout_request_id' => $mpesaResponse['CheckoutRequestID']
            ]);

            return $this->apiSuccess('Payment retry initiated! Please check your phone.', [
                'booking_id' => $booking->id,
                'mpesa_response' => $mpesaResponse
            ]);

        } catch (Exception $e) {
            return $this->apiError('Failed to retry payment', 500, ['error' => $e->getMessage()]);
        }
    }
}
