<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use App\Models\Bus;
use App\Models\BusBooking;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Events\SeatLockedEvent;
use App\Events\SeatUnlockedEvent;
use App\Notifications\Customer\BusBookingConfirmedNotification;
use App\Notifications\Customer\BusBookingPendingNotification;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\MpesaService;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Resources\Customer\BusResource;
use App\Http\Resources\Customer\BusBookingResource;
use Exception;

class BusController extends Controller
{
    use ApiResponseTrait;

    protected $mpesaService;

    public function __construct(MpesaService $mpesaService)
    {
        $this->mpesaService = $mpesaService;
    }

    /**
     * Search & list buses with filtering, dynamic seat availability, and pagination
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'departure_place'   => 'nullable|string',
            'destination_place' => 'nullable|string',
            'travel_date'       => 'nullable|date|after_or_equal:today',
            'bus_type'          => 'nullable|string',
            'min_price'         => 'nullable|numeric|min:0',
            'max_price'         => 'nullable|numeric|min:0',
            'sort_by'           => 'nullable|string|in:price_asc,price_desc,departure_time_asc,departure_time_desc',
            'per_page'          => 'nullable|integer|min:1|max:100',
        ]);

        $query = Bus::with(['images', 'merchantProfile'])->where('is_active', true);

        if (!empty($validated['departure_place'])) {
            $query->where('departure_place', 'like', '%' . $validated['departure_place'] . '%');
        }
        if (!empty($validated['destination_place'])) {
            $query->where('destination_place', 'like', '%' . $validated['destination_place'] . '%');
        }
        if (!empty($validated['bus_type'])) {
            $query->where('bus_type', $validated['bus_type']);
        }
        if (isset($validated['min_price'])) {
            $query->where('price_per_seat', '>=', $validated['min_price']);
        }
        if (isset($validated['max_price'])) {
            $query->where('price_per_seat', '<=', $validated['max_price']);
        }

        $sortBy = $validated['sort_by'] ?? 'departure_time_asc';
        switch ($sortBy) {
            case 'price_asc':
                $query->orderBy('price_per_seat', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('price_per_seat', 'desc');
                break;
            case 'departure_time_desc':
                $query->orderBy('departure_time', 'desc');
                break;
            case 'departure_time_asc':
            default:
                $query->orderBy('departure_time', 'asc');
                break;
        }

        $perPage = (int) ($validated['per_page'] ?? 15);
        $buses = $query->paginate($perPage);

        // Calculate dynamic seat availability for the given travel_date without N+1 queries
        $travelDate = $validated['travel_date'] ?? null;
        if ($travelDate && $buses->isNotEmpty()) {
            $busIds = $buses->pluck('id')->toArray();
            $activeBookings = BusBooking::whereIn('bus_id', $busIds)
                ->where('travel_date', $travelDate)
                ->where(function ($q) {
                    $q->where('status', 'paid')
                      ->orWhere(function ($sub) {
                          $sub->where('status', 'pending_payment')
                              ->where('locked_until', '>', Carbon::now());
                      });
                })
                ->get(['bus_id', 'seat_numbers']);

            $bookedSeatsPerBus = [];
            foreach ($activeBookings as $b) {
                $seats = is_array($b->seat_numbers) ? $b->seat_numbers : [];
                $bookedSeatsPerBus[$b->bus_id] = array_merge($bookedSeatsPerBus[$b->bus_id] ?? [], $seats);
            }

            foreach ($buses as $bus) {
                $uniqueBooked = array_values(array_unique($bookedSeatsPerBus[$bus->id] ?? []));
                $bookedCount = count($uniqueBooked);
                $availableCount = max(0, $bus->total_bookable_seats - $bookedCount);

                $bus->travel_date = $travelDate;
                $bus->booked_seats_count = $bookedCount;
                $bus->available_seats_count = $availableCount;
                $bus->is_sold_out = ($availableCount === 0);
            }
        }

        return $this->apiSuccess('Buses retrieved successfully', [
            'buses' => BusResource::collection($buses),
            'pagination' => [
                'current_page' => $buses->currentPage(),
                'per_page'     => $buses->perPage(),
                'total'        => $buses->total(),
                'last_page'    => $buses->lastPage(),
                'has_more'     => $buses->hasMorePages(),
            ]
        ]);
    }

    /**
     * Generate Seat Map for a bus on a specific travel date
     */
    public function seatMap(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'travel_date' => 'required|date|after_or_equal:today',
        ]);

        $bus = Bus::with(['images', 'merchantProfile'])->findOrFail($id);
        $seatMap = $this->generateSeatMap($bus, $validated['travel_date']);

        $bookedSeats = $this->getBookedSeats($bus->id, $validated['travel_date']);
        $bookedSeatsCount = count($bookedSeats);
        $availableSeatsCount = max(0, $bus->total_bookable_seats - $bookedSeatsCount);

        $bus->travel_date = $validated['travel_date'];
        $bus->available_seats_count = $availableSeatsCount;
        $bus->booked_seats_count = $bookedSeatsCount;
        $bus->is_sold_out = ($availableSeatsCount === 0);

        return $this->apiSuccess('Seat map generated successfully', [
            'bus' => new BusResource($bus),
            'travel_date' => $validated['travel_date'],
            'total_bookable_seats' => $bus->total_bookable_seats,
            'available_seats_count' => $availableSeatsCount,
            'booked_seats_count' => $bookedSeatsCount,
            'booked_seats' => $bookedSeats,
            'driver_position' => $bus->driver_position,
            'has_middle_door' => (bool) $bus->has_middle_door,
            'seat_map' => $seatMap,
        ]);
    }

    /**
     * Helper to get booked/locked seats
     */
    private function getBookedSeats(int $busId, string $travelDate): array
    {
        $bookings = BusBooking::where('bus_id', $busId)
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

        return array_values(array_unique($bookedSeats));
    }

    /**
     * Helper to get booked seats with row locking inside transaction
     */
    private function getBookedSeatsForUpdate(int $busId, string $travelDate): array
    {
        $bookings = BusBooking::where('bus_id', $busId)
            ->where('travel_date', $travelDate)
            ->where(function ($query) {
                $query->where('status', 'paid')
                      ->orWhere(function ($q) {
                          $q->where('status', 'pending_payment')
                            ->where('locked_until', '>', Carbon::now());
                      });
            })
            ->lockForUpdate()
            ->get();

        $bookedSeats = [];
        foreach ($bookings as $b) {
            $bookedSeats = array_merge($bookedSeats, $b->seat_numbers);
        }

        return array_values(array_unique($bookedSeats));
    }

    /**
     * Generate seat matrix representation
     */
    private function generateSeatMap(Bus $bus, string $travelDate): array
    {
        $bookedSeats = $this->getBookedSeats($bus->id, $travelDate);

        $seatMap = [];
        $pattern = explode('-', $bus->seat_pattern); // e.g. ['2', '2']
        $leftCols = (int) $pattern[0];
        $rightCols = count($pattern) > 1 ? (int) $pattern[1] : 0;
        
        $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

        for ($row = 1; $row <= $bus->total_rows; $row++) {
            $rowData = [
                'row' => $row,
                'is_back_row' => false,
                'left' => [],
                'right' => []
            ];

            // If it's the last row and has back_row_seats
            if ($row == $bus->total_rows && $bus->back_row_seats > 0) {
                $rowData['is_back_row'] = true;
                $rowData['back_seats'] = [];
                for ($i = 0; $i < $bus->back_row_seats; $i++) {
                    $seatId = $letters[$i] . $row;
                    $seatType = ($i === 0 || $i === $bus->back_row_seats - 1) ? 'window' : 'middle';
                    $rowData['back_seats'][] = [
                        'id' => $seatId,
                        'seat_number' => $seatId,
                        'seat_type' => $seatType,
                        'is_available' => !in_array($seatId, $bookedSeats)
                    ];
                }
            } else {
                for ($i = 0; $i < $leftCols; $i++) {
                    $seatId = $letters[$i] . $row;
                    $seatType = ($i === 0) ? 'window' : (($i === $leftCols - 1) ? 'aisle' : 'middle');
                    $rowData['left'][] = [
                        'id' => $seatId,
                        'seat_number' => $seatId,
                        'seat_type' => $seatType,
                        'is_available' => !in_array($seatId, $bookedSeats)
                    ];
                }
                for ($i = 0; $i < $rightCols; $i++) {
                    $seatId = $letters[$leftCols + $i] . $row;
                    $seatType = ($i === $rightCols - 1) ? 'window' : (($i === 0) ? 'aisle' : 'middle');
                    $rowData['right'][] = [
                        'id' => $seatId,
                        'seat_number' => $seatId,
                        'seat_type' => $seatType,
                        'is_available' => !in_array($seatId, $bookedSeats)
                    ];
                }
            }

            $seatMap[] = $rowData;
        }

        return $seatMap;
    }

    /**
     * Create bus booking and initiate payment (M-Pesa STK push or instant Wallet deduction)
     */
    public function book(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bus_id' => 'required|exists:buses,id',
            'travel_date' => 'required|date|after_or_equal:today',
            'seat_numbers' => 'required|array|min:1|max:6',
            'seat_numbers.*' => 'string',
            'passenger_name' => 'nullable|string|max:255',
            'passenger_phone' => 'nullable|string|max:50',
            'passenger_email' => 'nullable|email|max:255',
            'payment_method' => 'nullable|string|in:mpesa,wallet',
            'mpesa_number' => 'nullable|string|max:50',
            'phone_number' => 'nullable|string|max:50',
        ]);

        $bus = Bus::findOrFail($validated['bus_id']);
        $user = $request->user();

        $passengerName = $validated['passenger_name'] ?? $user->name;
        $passengerPhone = $validated['passenger_phone'] ?? $validated['mpesa_number'] ?? $validated['phone_number'] ?? $user->phone_number ?? '';
        $passengerEmail = $validated['passenger_email'] ?? $user->email;
        $paymentMethod = $validated['payment_method'] ?? 'mpesa';

        DB::beginTransaction();
        try {
            // Check availability with database row lock
            $bookedSeats = $this->getBookedSeatsForUpdate($bus->id, $validated['travel_date']);

            foreach ($validated['seat_numbers'] as $seat) {
                if (in_array($seat, $bookedSeats)) {
                    throw new Exception("Seat {$seat} is already taken or locked.");
                }
            }

            $totalPrice = $bus->price_per_seat * count($validated['seat_numbers']);

            $booking = BusBooking::create([
                'user_id' => $user->id,
                'merchant_profile_id' => $bus->merchant_profile_id,
                'bus_id' => $bus->id,
                'travel_date' => $validated['travel_date'],
                'seat_numbers' => $validated['seat_numbers'],
                'passenger_name' => $passengerName,
                'passenger_phone' => $passengerPhone,
                'passenger_email' => $passengerEmail,
                'total_price' => $totalPrice,
                'payment_method' => $paymentMethod,
                'status' => 'pending_payment',
                'locked_until' => Carbon::now()->addMinutes(15),
            ]);

            // Broadcast seat lock to other clients
            event(new SeatLockedEvent($bus->id, $validated['travel_date'], $validated['seat_numbers']));

            // Handle WALLET payment
            if ($paymentMethod === 'wallet') {
                $walletResult = $this->processWalletPayment($user, $booking, $bus, $totalPrice);
                DB::commit();

                return $this->apiSuccess('Booking confirmed and paid successfully via Wallet!', [
                    'booking' => new BusBookingResource($booking->fresh(['bus.images', 'merchantProfile'])),
                    'payment_method' => 'wallet',
                    'wallet_balance' => (float) $walletResult['new_balance'],
                ], 201);
            }

            // Handle M-PESA payment
            $paymentPhone = $validated['mpesa_number'] ?? $validated['phone_number'] ?? $passengerPhone;
            if (empty($paymentPhone)) {
                throw new Exception('A valid M-Pesa phone number is required for M-Pesa payment.');
            }

            $mpesaResponse = $this->mpesaService->initiateStkPush(
                $paymentPhone,
                $totalPrice,
                'BUS-' . $booking->id,
                'ChapPlus Bus Booking'
            );

            $booking->update([
                'mpesa_checkout_request_id' => $mpesaResponse['CheckoutRequestID'] ?? null
            ]);

            DB::commit();

            // Send notification to customer for pending seat reservation
            $user->notify(new BusBookingPendingNotification($booking));

            return $this->apiSuccess('Seats locked successfully. Please enter your M-Pesa PIN on your phone to complete payment.', [
                'booking' => new BusBookingResource($booking->fresh(['bus.images', 'merchantProfile'])),
                'payment_method' => 'mpesa',
                'payment_instructions' => [
                    'method'       => 'mpesa',
                    'prompt_phone' => $paymentPhone,
                    'message'      => "An STK payment prompt has been sent to {$paymentPhone}. Please enter your M-Pesa PIN to complete payment.",
                ],
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            return $this->apiError('Failed to initiate booking: ' . $e->getMessage(), 409, ['code' => 'SEAT_CONFLICT']);
        }
    }

    /**
     * Process instant wallet deduction, status update, commission, and notifications
     */
    private function processWalletPayment(User $user, BusBooking $booking, Bus $bus, float $totalPrice): array
    {
        $wallet = Wallet::firstOrCreate(['user_id' => $user->id]);

        if ($wallet->balance < $totalPrice) {
            throw new Exception("Insufficient wallet balance. You have {$wallet->currency} {$wallet->balance}, but the total is {$totalPrice}.");
        }

        // 1. Deduct customer wallet
        $wallet->decrement('balance', $totalPrice);
        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'type' => 'debit',
            'amount' => $totalPrice,
            'reference_type' => BusBooking::class,
            'reference_id' => $booking->id,
            'description' => "Payment for Bus Booking #{$booking->id} ({$bus->name})",
        ]);

        // 2. Mark booking paid
        $booking->update([
            'status' => 'paid',
            'payment_method' => 'wallet',
        ]);

        // 3. Instant commission split: Admin commission & Merchant earnings
        $merchantCommissionPercent = PlatformSetting::getCommissionRate('bus');
        $adminCommission = $totalPrice * ($merchantCommissionPercent / 100);
        $merchantEarnings = $totalPrice - $adminCommission;

        // Credit Admin Wallet
        $adminUser = User::role('ADMIN')->first();
        if ($adminUser) {
            $adminWallet = Wallet::firstOrCreate(['user_id' => $adminUser->id]);
            $adminWallet->increment('balance', $adminCommission);
            WalletTransaction::create([
                'wallet_id' => $adminWallet->id,
                'type' => 'credit',
                'amount' => $adminCommission,
                'reference_type' => BusBooking::class,
                'reference_id' => $booking->id,
                'description' => "Platform commission for Bus Booking #{$booking->id}",
            ]);
        }

        // Credit Merchant Wallet
        $merchantUser = $bus->merchantProfile?->user;
        if ($merchantUser) {
            $merchantWallet = Wallet::firstOrCreate(['user_id' => $merchantUser->id]);
            $merchantWallet->increment('balance', $merchantEarnings);
            WalletTransaction::create([
                'wallet_id' => $merchantWallet->id,
                'type' => 'credit',
                'amount' => $merchantEarnings,
                'reference_type' => BusBooking::class,
                'reference_id' => $booking->id,
                'description' => "Earnings for Bus Booking #{$booking->id}",
            ]);
        }

        // 4. Send multi-channel notification
        $user->notify(new BusBookingConfirmedNotification($booking));

        return [
            'new_balance' => (float) $wallet->fresh()->balance
        ];
    }

    /**
     * Get Customer's Bus Bookings list
     */
    public function myBookings(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 15);
        $bookings = BusBooking::with(['bus.images', 'merchantProfile'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate($perPage);

        return $this->apiSuccess('Bookings retrieved successfully', [
            'bookings' => BusBookingResource::collection($bookings),
            'pagination' => [
                'current_page' => $bookings->currentPage(),
                'per_page'     => $bookings->perPage(),
                'total'        => $bookings->total(),
                'last_page'    => $bookings->lastPage(),
                'has_more'     => $bookings->hasMorePages(),
            ]
        ]);
    }

    /**
     * Get Single Bus Booking Details (Matching Screen 3 / Confirmation)
     */
    public function showBooking(Request $request, string $id): JsonResponse
    {
        $booking = BusBooking::with(['bus.images', 'merchantProfile'])
            ->where('user_id', $request->user()->id)
            ->find($id);

        if (!$booking) {
            return $this->apiError('Booking not found', 404);
        }

        return $this->apiSuccess('Booking details retrieved successfully', [
            'booking' => new BusBookingResource($booking)
        ]);
    }

    /**
     * Download or stream official PDF E-Ticket / Boarding Pass
     */
    public function downloadTicket(Request $request, string $id)
    {
        $booking = BusBooking::with(['bus', 'merchantProfile.user'])
            ->where('user_id', $request->user()->id)
            ->find($id);

        if (!$booking) {
            return $this->apiError('Booking not found', 404);
        }

        if ($booking->status !== 'paid') {
            return $this->apiError('Ticket PDF is only available for paid and confirmed bookings.', 400, [
                'current_status' => $booking->status
            ]);
        }

        $bookingId = '#BUS-' . str_pad($booking->id, 5, '0', STR_PAD_LEFT);
        $bus = $booking->bus;
        $merchantName = $booking->merchantProfile?->business_name ?? 'Bus Operator';
        $seatNumbers = is_array($booking->seat_numbers) ? $booking->seat_numbers : [$booking->seat_numbers];

        $travelDateFormatted = $booking->travel_date
            ? Carbon::parse($booking->travel_date)->format('D, M d, Y')
            : date('D, M d, Y');

        $pdfData = [
            'booking'             => $booking,
            'bookingId'           => $bookingId,
            'busName'             => $bus?->name ?? 'Express Coach',
            'merchantName'        => $merchantName,
            'fromCity'            => $bus?->from_city ?? $bus?->departure_place ?? 'Departure',
            'departurePlace'      => $bus?->departure_place ?? 'Terminal',
            'toCity'              => $bus?->to_city ?? $bus?->destination_place ?? 'Destination',
            'destinationPlace'    => $bus?->destination_place ?? 'Terminal',
            'departureTime'       => $bus?->departure_time ?? 'Scheduled',
            'destinationTime'     => $bus?->destination_time ?? 'Estimated',
            'journeyDuration'     => $bus?->journey_duration ?? 'Direct',
            'travelDateFormatted' => $travelDateFormatted,
            'passengerName'       => $booking->passenger_name ?? $request->user()->name ?? 'Passenger',
            'passengerPhone'      => $booking->passenger_phone ?? $request->user()->phone_number ?? '',
            'passengerEmail'      => $booking->passenger_email ?? $request->user()->email ?? '',
            'seatNumbers'         => $seatNumbers,
            'totalPrice'          => (float) $booking->total_price,
            'paymentMethod'       => $booking->payment_method ?? 'mpesa',
            'mpesaReceipt'        => $booking->mpesa_receipt_number ?? '',
            'issuedAt'            => now()->format('Y-m-d H:i:s T'),
        ];

        $pdf = Pdf::loadView('tickets.bus_ticket_pdf', $pdfData);
        $cleanCode = str_replace('#', '', $bookingId);
        $fileName = "ChapPlus-Ticket-{$cleanCode}.pdf";

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }

    /**
     * Initiate bus booking (alias for book)
     */
    public function initiateBooking(Request $request): JsonResponse
    {
        return $this->book($request);
    }

    /**
     * Cancel pending booking and release locked seats immediately
     */
    public function cancelBooking(Request $request, string $id): JsonResponse
    {
        $booking = BusBooking::where('user_id', $request->user()->id)->find($id);

        if (!$booking) {
            return $this->apiError('Booking not found', 404);
        }

        if ($booking->status === 'paid') {
            return $this->apiError('Cannot cancel a paid booking directly. Please request a refund.', 400);
        }

        if ($booking->status === 'cancelled') {
            return $this->apiSuccess('Booking is already cancelled.');
        }

        $booking->update([
            'status' => 'cancelled',
            'locked_until' => null,
        ]);

        // Release locked seats immediately via Reverb event
        event(new SeatUnlockedEvent(
            $booking->bus_id,
            $booking->travel_date->format('Y-m-d'),
            $booking->seat_numbers
        ));

        return $this->apiSuccess('Booking cancelled and seats released successfully.');
    }

    /**
     * Retry payment for a pending or failed booking safely.
     * Atomically checks if the reserved seats are still available.
     */
    public function retryPayment(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'payment_method' => 'nullable|string|in:mpesa,wallet',
            'mpesa_number' => 'nullable|string|max:50',
            'phone_number' => 'nullable|string|max:50',
        ]);

        $booking = BusBooking::with(['bus.images', 'merchantProfile'])
            ->where('user_id', $request->user()->id)
            ->find($id);

        if (!$booking) {
            return $this->apiError('Booking not found', 404);
        }

        if ($booking->status === 'paid') {
            return $this->apiError('This booking is already paid.', 400);
        }

        $paymentMethod = $validated['payment_method'] ?? $booking->payment_method ?? 'mpesa';

        DB::beginTransaction();
        try {
            $travelDate = $booking->travel_date instanceof Carbon 
                ? $booking->travel_date->format('Y-m-d') 
                : Carbon::parse($booking->travel_date)->format('Y-m-d');

            // 1. Check if any seats were claimed by another booking
            $conflictingBookings = BusBooking::where('bus_id', $booking->bus_id)
                ->where('travel_date', $travelDate)
                ->where('id', '!=', $booking->id)
                ->where(function ($query) {
                    $query->where('status', 'paid')
                          ->orWhere(function ($q) {
                              $q->where('status', 'pending_payment')
                                ->where('locked_until', '>', Carbon::now());
                          });
                })
                ->lockForUpdate()
                ->get();

            $claimedSeats = [];
            foreach ($conflictingBookings as $cb) {
                $claimedSeats = array_merge($claimedSeats, $cb->seat_numbers);
            }
            $claimedSeats = array_unique($claimedSeats);

            $conflictingSeats = array_values(array_intersect($booking->seat_numbers, $claimedSeats));

            if (!empty($conflictingSeats)) {
                DB::rollBack();
                return $this->apiError(
                    'Seats (' . implode(', ', $conflictingSeats) . ') have already been taken by another passenger.',
                    409,
                    [
                        'code' => 'SEATS_NO_LONGER_AVAILABLE',
                        'conflicting_seats' => $conflictingSeats,
                        'booking_id' => $booking->id,
                        'bus_id' => $booking->bus_id,
                        'travel_date' => $travelDate,
                    ]
                );
            }

            // 2. Seats are free! Re-lock seats for 15 minutes
            $booking->update([
                'status' => 'pending_payment',
                'locked_until' => Carbon::now()->addMinutes(15),
                'payment_method' => $paymentMethod,
            ]);

            // Broadcast seat locked event to keep other users' seat maps updated
            event(new SeatLockedEvent(
                $booking->bus_id,
                $travelDate,
                $booking->seat_numbers
            ));

            // 3. If Wallet Payment
            if ($paymentMethod === 'wallet') {
                $walletResult = $this->processWalletPayment($request->user(), $booking, $booking->bus, (float) $booking->total_price);
                DB::commit();

                // Multi-channel notification on successful payment
                try {
                    $request->user()->notify(new BusBookingConfirmedNotification($booking));
                } catch (\Throwable $e) {
                    Log::error("Failed to send BusBookingConfirmedNotification on retry: " . $e->getMessage());
                }

                return $this->apiSuccess('Booking paid successfully via Wallet!', [
                    'booking' => new BusBookingResource($booking->fresh(['bus.images', 'merchantProfile'])),
                    'payment_method' => 'wallet',
                    'wallet_balance' => (float) $walletResult['new_balance'],
                ]);
            }

            DB::commit();

            // 4. If M-Pesa Payment
            $paymentPhone = $validated['mpesa_number'] ?? $validated['phone_number'] ?? $booking->passenger_phone ?? $request->user()->phone_number;
            if (empty($paymentPhone)) {
                return $this->apiError('M-Pesa number is required for M-Pesa payment.', 400);
            }

            $mpesaResponse = $this->mpesaService->initiateStkPush(
                $paymentPhone,
                $booking->total_price,
                'BUS-' . $booking->id,
                'ChapPlus Bus Retry'
            );

            $booking->update([
                'mpesa_checkout_request_id' => $mpesaResponse['CheckoutRequestID'] ?? null
            ]);

            return $this->apiSuccess('M-Pesa payment retry initiated! Please enter your PIN on your phone.', [
                'booking' => new BusBookingResource($booking->fresh(['bus.images', 'merchantProfile'])),
                'payment_method' => 'mpesa',
                'payment_instructions' => [
                    'method'       => 'mpesa',
                    'prompt_phone' => $paymentPhone,
                    'message'      => "An STK payment prompt has been sent to {$paymentPhone}. Please enter your M-Pesa PIN to complete payment.",
                ],
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Bus booking retry payment error for #{$booking->id}: " . $e->getMessage());
            return $this->apiError('Failed to retry payment: ' . $e->getMessage(), 500);
        }
    }
}
