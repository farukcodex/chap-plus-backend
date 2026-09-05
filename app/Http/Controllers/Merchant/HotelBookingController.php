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

        $query = HotelBooking::with(['hotel', 'user.userProfile'])
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
            'status' => 'required|in:confirmed,checked_in,checked_out,cancelled'
        ]);

        $booking = HotelBooking::where('merchant_profile_id', $merchantProfile->id)->find($id);

        if (!$booking) {
            return $this->apiError('Booking not found', 404);
        }

        // Strict State Machine Validation
        $allowedTransitions = [
            'paid' => ['confirmed', 'cancelled'],
            'confirmed' => ['checked_in', 'cancelled'],
            'checked_in' => ['checked_out', 'cancelled'],
        ];

        $currentStatus = $booking->status;
        $newStatus = $validated['status'];

        if ($currentStatus === $newStatus) {
            return $this->apiSuccess('Booking is already in this status', ['booking' => $booking]);
        }

        if (!array_key_exists($currentStatus, $allowedTransitions)) {
            return $this->apiError("This booking is in a terminal state ({$currentStatus}) and cannot be updated.", 400);
        }

        if (!in_array($newStatus, $allowedTransitions[$currentStatus])) {
            return $this->apiError("Invalid status transition. Cannot change from {$currentStatus} to {$newStatus}.", 400);
        }

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();
            
            $merchantCommissionPercent = \App\Models\PlatformSetting::where('key', 'merchant_commission_percent')->value('value') ?? 10.00;
            $adminCommission = $booking->total_price * ($merchantCommissionPercent / 100);
            $merchantEarnings = $booking->total_price - $adminCommission;

            // 1. Escrow Release: Credit Wallets on Check-In
            if ($newStatus === 'checked_in' && $currentStatus === 'confirmed') {
                $adminUser = \App\Models\User::role('ADMIN')->first();
                if ($adminUser) {
                    $adminWallet = \App\Models\Wallet::firstOrCreate(['user_id' => $adminUser->id]);
                    $adminWallet->increment('balance', $adminCommission);
                    \App\Models\WalletTransaction::create([
                        'wallet_id' => $adminWallet->id,
                        'type' => 'credit',
                        'amount' => $adminCommission,
                        'reference_type' => \App\Models\HotelBooking::class,
                        'reference_id' => $booking->id,
                        'description' => "Platform commission for Hotel Booking #{$booking->id}",
                    ]);
                }

                $merchantWallet = \App\Models\Wallet::firstOrCreate(['user_id' => $merchantProfile->user_id]);
                $merchantWallet->increment('balance', $merchantEarnings);
                \App\Models\WalletTransaction::create([
                    'wallet_id' => $merchantWallet->id,
                    'type' => 'credit',
                    'amount' => $merchantEarnings,
                    'reference_type' => \App\Models\HotelBooking::class,
                    'reference_id' => $booking->id,
                    'description' => "Earnings for Hotel Booking #{$booking->id}",
                ]);
            }

            // 2. Cancellation Logic
            if ($newStatus === 'cancelled') {
                // If they cancel AFTER check-in, they already received the escrow money. Claw it back.
                if ($currentStatus === 'checked_in') {
                    // Clawback Admin Wallet
                    $adminUser = \App\Models\User::role('ADMIN')->first();
                    if ($adminUser) {
                        $adminWallet = \App\Models\Wallet::where('user_id', $adminUser->id)->first();
                        if ($adminWallet) {
                            $adminWallet->decrement('balance', $adminCommission);
                            \App\Models\WalletTransaction::create([
                                'wallet_id' => $adminWallet->id,
                                'type' => 'debit',
                                'amount' => $adminCommission,
                                'reference_type' => \App\Models\HotelBooking::class,
                                'reference_id' => $booking->id,
                                'description' => "Clawback platform commission for Cancelled Hotel Booking #{$booking->id}",
                            ]);
                        }
                    }

                    // Clawback Merchant Wallet
                    $merchantWallet = \App\Models\Wallet::where('user_id', $merchantProfile->user_id)->first();
                    if ($merchantWallet) {
                        if ($merchantWallet->balance < $merchantEarnings) {
                            return $this->apiError("Cannot cancel booking. Your wallet balance ({$merchantWallet->balance}) is too low to cover the required clawback of {$merchantEarnings}.", 400);
                        }
                        $merchantWallet->decrement('balance', $merchantEarnings);
                        \App\Models\WalletTransaction::create([
                            'wallet_id' => $merchantWallet->id,
                            'type' => 'debit',
                            'amount' => $merchantEarnings,
                            'reference_type' => \App\Models\HotelBooking::class,
                            'reference_id' => $booking->id,
                            'description' => "Clawback earnings for Cancelled Hotel Booking #{$booking->id}",
                        ]);
                    }
                }
                
                // For ANY cancellation (paid, confirmed, or checked_in), generate a Refund record for the Customer
                \App\Models\Refund::create([
                    'user_id' => $booking->user_id,
                    'refundable_type' => \App\Models\HotelBooking::class,
                    'refundable_id' => $booking->id,
                    'amount' => $booking->total_price,
                    'status' => 'pending',
                ]);
            }

            $booking->update(['status' => $newStatus]);

            \Illuminate\Support\Facades\DB::commit();

            return $this->apiSuccess('Booking status updated to ' . $newStatus, ['booking' => $booking]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return $this->apiError('Failed to update booking status: ' . $e->getMessage(), 500);
        }
    }
}
