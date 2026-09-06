<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use App\Models\Bus;
use App\Models\BusBooking;
use App\Events\SeatLockedEvent;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\MpesaService;

class BusController extends Controller
{
    use ApiResponseTrait;

    protected $mpesaService;

    public function __construct(MpesaService $mpesaService)
    {
        $this->mpesaService = $mpesaService;
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'departure_place' => 'nullable|string',
            'destination_place' => 'nullable|string',
            'travel_date' => 'nullable|date|after_or_equal:today',
        ]);

        $query = Bus::with(['images', 'merchantProfile.user'])->where('is_active', true);

        if (!empty($validated['departure_place'])) {
            $query->where('departure_place', 'like', '%' . $validated['departure_place'] . '%');
        }
        if (!empty($validated['destination_place'])) {
            $query->where('destination_place', 'like', '%' . $validated['destination_place'] . '%');
        }

        $buses = $query->get();

        return $this->apiSuccess('Buses retrieved successfully', ['buses' => $buses]);
    }

    public function seatMap(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'travel_date' => 'required|date|after_or_equal:today',
        ]);

        $bus = Bus::findOrFail($id);
        $seatMap = $this->generateSeatMap($bus, $validated['travel_date']);

        return $this->apiSuccess('Seat map generated successfully', [
            'bus' => $bus,
            'travel_date' => $validated['travel_date'],
            'seat_map' => $seatMap,
        ]);
    }

    private function generateSeatMap(Bus $bus, string $travelDate): array
    {
        // 1. Fetch locked/paid seats
        $bookings = BusBooking::where('bus_id', $bus->id)
            ->where('travel_date', $travelDate)
            ->where(function ($query) {
                $query->where('status', 'paid')
                      ->orWhere(function ($q) {
                          $q->where('status', 'pending_payment')
                            ->where('locked_until', '>', Carbon::now());
                      });
            })
            ->get();

        $bookedSeats = [];
        foreach ($bookings as $b) {
            $bookedSeats = array_merge($bookedSeats, $b->seat_numbers);
        }

        // 2. Generate Seat Matrix
        $seatMap = [];
        $pattern = explode('-', $bus->seat_pattern); // e.g., ['2', '2']
        $leftCols = (int)$pattern[0];
        $rightCols = count($pattern) > 1 ? (int)$pattern[1] : 0;
        
        $letters = ['A', 'B', 'C', 'D', 'E', 'F'];

        for ($row = 1; $row <= $bus->total_rows; $row++) {
            $rowData = [
                'row' => $row,
                'left' => [],
                'right' => []
            ];

            // If it's the last row, it might have back_row_seats
            if ($row == $bus->total_rows && $bus->back_row_seats > 0) {
                // Generate consecutive seats for back row
                for ($i = 0; $i < $bus->back_row_seats; $i++) {
                    $seatId = $letters[$i] . $row;
                    $rowData['left'][] = [
                        'id' => $seatId,
                        'is_available' => !in_array($seatId, $bookedSeats)
                    ];
                }
            } else {
                // Normal Row
                for ($i = 0; $i < $leftCols; $i++) {
                    $seatId = $letters[$i] . $row;
                    $rowData['left'][] = [
                        'id' => $seatId,
                        'is_available' => !in_array($seatId, $bookedSeats)
                    ];
                }
                for ($i = 0; $i < $rightCols; $i++) {
                    $seatId = $letters[$leftCols + $i] . $row;
                    $rowData['right'][] = [
                        'id' => $seatId,
                        'is_available' => !in_array($seatId, $bookedSeats)
                    ];
                }
            }

            $seatMap[] = $rowData;
        }

        return $seatMap;
    }

    public function initiateBooking(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bus_id' => 'required|exists:buses,id',
            'travel_date' => 'required|date|after_or_equal:today',
            'seat_numbers' => 'required|array|min:1|max:4',
            'seat_numbers.*' => 'string',
            'phone_number' => 'required|string',
        ]);

        $bus = Bus::findOrFail($validated['bus_id']);
        $user = $request->user();

        // Start Transaction to prevent race conditions
        DB::beginTransaction();
        try {
            // Check availability
            $existingBookings = BusBooking::where('bus_id', $bus->id)
                ->where('travel_date', $validated['travel_date'])
                ->where(function ($query) {
                    $query->where('status', 'paid')
                          ->orWhere(function ($q) {
                              $q->where('status', 'pending_payment')
                                ->where('locked_until', '>', Carbon::now());
                          });
                })
                ->lockForUpdate() // Database level row-locking
                ->get();

            $bookedSeats = [];
            foreach ($existingBookings as $b) {
                $bookedSeats = array_merge($bookedSeats, $b->seat_numbers);
            }

            foreach ($validated['seat_numbers'] as $seat) {
                if (in_array($seat, $bookedSeats)) {
                    throw new \Exception("Seat {$seat} is already taken or locked.");
                }
            }

            // Create Booking Lock
            $totalPrice = $bus->price_per_seat * count($validated['seat_numbers']);
            $booking = BusBooking::create([
                'user_id' => $user->id,
                'merchant_profile_id' => $bus->merchant_profile_id,
                'bus_id' => $bus->id,
                'travel_date' => $validated['travel_date'],
                'seat_numbers' => $validated['seat_numbers'],
                'total_price' => $totalPrice,
                'status' => 'pending_payment',
                'locked_until' => Carbon::now()->addMinutes(15)
            ]);

            // Initiate M-Pesa STK Push
            $mpesaResponse = $this->mpesaService->initiateStkPush(
                $validated['phone_number'],
                $totalPrice,
                'BUS-' . $booking->id,
                'ChapPlus Bus Booking'
            );

            $booking->update([
                'mpesa_checkout_request_id' => $mpesaResponse['CheckoutRequestID']
            ]);

            DB::commit();

            // Broadcast to others!
            event(new SeatLockedEvent($bus->id, $validated['travel_date'], $validated['seat_numbers']));

            return $this->apiSuccess('Seats locked successfully. Please enter your M-Pesa PIN on your phone to complete payment.', [
                'booking' => $booking,
                'mpesa_response' => $mpesaResponse
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiError('Failed to initiate booking: ' . $e->getMessage(), 409, ['code' => 'SEAT_CONFLICT']);
        }
    }

    public function retryPayment(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => 'required|string',
        ]);

        $booking = BusBooking::where('user_id', $request->user()->id)->find($id);

        if (!$booking) {
            return $this->apiError('Booking not found', 404);
        }

        if ($booking->status === 'paid') {
            return $this->apiError('This booking is already paid.', 400);
        }

        if ($booking->locked_until && $booking->locked_until < Carbon::now()) {
            return $this->apiError('Seat reservation has expired. Please book again.', 400);
        }

        try {
            // Initiate M-Pesa STK Push
            $mpesaResponse = $this->mpesaService->initiateStkPush(
                $validated['phone_number'],
                $booking->total_price,
                'BUS-' . $booking->id,
                'ChapPlus Bus Booking Retry'
            );

            // Update the request ID
            $booking->update([
                'mpesa_checkout_request_id' => $mpesaResponse['CheckoutRequestID']
            ]);

            return $this->apiSuccess('Payment retry initiated! Please check your phone.', [
                'booking_id' => $booking->id,
                'mpesa_response' => $mpesaResponse
            ]);

        } catch (\Exception $e) {
            Log::error("Bus Retry Payment Error: " . $e->getMessage());
            return $this->apiError('Failed to retry payment: ' . $e->getMessage(), 500);
        }
    }
}
