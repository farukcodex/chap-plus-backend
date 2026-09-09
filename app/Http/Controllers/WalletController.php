<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\PayoutRequest;
use App\Http\Resources\Customer\PayoutResource;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $wallet = Wallet::firstOrCreate(['user_id' => $request->user()->id]);

        $todayEarnings = WalletTransaction::where('wallet_id', $wallet->id)
            ->where('type', 'credit')
            ->whereDate('created_at', today())
            ->sum('amount');

        $thisWeekEarnings = WalletTransaction::where('wallet_id', $wallet->id)
            ->where('type', 'credit')
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->sum('amount');

        $data = [
            'balance' => $wallet->balance,
            'currency' => $wallet->currency,
            'today_earnings' => $todayEarnings,
            'this_week_earnings' => $thisWeekEarnings,
        ];

        if ($request->user()->hasRole('RIDER')) {
            $data['deliveries'] = \App\Models\Order::where('rider_id', $request->user()->id)
                ->where('status', 'delivered')->count();
        } elseif ($request->user()->hasRole('ECOMMERCE_MERCHANT')) {
            $merchantProfile = $request->user()->merchantProfile;
            if ($merchantProfile) {
                $data['total_orders'] = \App\Models\Order::where('merchant_profile_id', $merchantProfile->id)
                    ->where('status', 'delivered')->count();
            } else {
                $data['total_orders'] = 0;
            }
        }

        return $this->apiSuccess('Wallet details retrieved', $data);
    }

    public function transactions(Request $request): JsonResponse
    {
        $wallet = Wallet::firstOrCreate(['user_id' => $request->user()->id]);

        $transactions = WalletTransaction::where('wallet_id', $wallet->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        return $this->apiSuccess('Transactions retrieved', $transactions);
    }

    public function requestPayout(Request $request): JsonResponse
    {
        $user = $request->user();

        // Only merchants and riders can request payouts
        $allowedRoles = ['ECOMMERCE_MERCHANT', 'RESTAURANT_MERCHANT', 'HOTEL_MERCHANT', 'BUS_MERCHANT', 'RIDER'];
        if (!$user->hasAnyRole($allowedRoles)) {
            return $this->apiError('Only registered merchants and riders can request payouts.', 403);
        }

        $request->validate([
            'amount' => 'required|numeric|min:100',
            'mpesa_number' => 'required|string',
        ]);

        $wallet = Wallet::firstOrCreate(['user_id' => $user->id]);

        if ($wallet->balance < $request->amount) {
            return $this->apiError('Insufficient balance for payout', 400);
        }

        // Prevent duplicate pending payout requests
        $hasPending = PayoutRequest::where('user_id', $user->id)->where('status', 'pending')->exists();
        if ($hasPending) {
            return $this->apiError('You already have a pending payout request. Please wait for it to be processed or cancel it before submitting a new one.', 400);
        }

        $payout = null;

        // Deduct from wallet and create payout request in a transaction
        \Illuminate\Support\Facades\DB::transaction(function () use ($wallet, $request, &$payout) {
            $wallet->decrement('balance', $request->amount);

            $payout = PayoutRequest::create([
                'user_id' => $request->user()->id,
                'amount' => $request->amount,
                'status' => 'pending',
                'payout_method' => 'mpesa',
                'mpesa_number' => $request->mpesa_number,
            ]);

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'debit',
                'amount' => $request->amount,
                'reference_type' => PayoutRequest::class,
                'reference_id' => $payout->id,
                'description' => 'M-Pesa Payout Request',
            ]);
        });

        return $this->apiSuccess('Payout request submitted successfully!', [
            'payout' => new PayoutResource($payout)
        ], 201);
    }

    /**
     * Cancel a pending payout request and refund funds back to user wallet.
     */
    public function cancelPayout(Request $request, string $id): JsonResponse
    {
        $payout = PayoutRequest::where('user_id', $request->user()->id)->find($id);

        if (!$payout) {
            return $this->apiError('Payout request not found', 404);
        }

        if ($payout->status !== 'pending') {
            return $this->apiError("You can only cancel pending payout requests. Current status: {$payout->status}", 400);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($payout, $request) {
            $payout->update([
                'status'           => 'rejected',
                'rejection_reason' => 'Cancelled by user',
                'processed_at'     => now(),
            ]);

            $wallet = Wallet::firstOrCreate(['user_id' => $request->user()->id]);
            $wallet->increment('balance', $payout->amount);

            WalletTransaction::create([
                'wallet_id'      => $wallet->id,
                'type'           => 'credit',
                'amount'         => $payout->amount,
                'reference_type' => PayoutRequest::class,
                'reference_id'   => $payout->id,
                'description'    => 'Payout Request Cancelled by User',
            ]);
        });

        return $this->apiSuccess('Payout request cancelled and funds returned to wallet', [
            'payout' => new PayoutResource($payout)
        ]);
    }

    public function payouts(Request $request): JsonResponse
    {
        $payouts = PayoutRequest::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        $payouts->through(fn($payout) => (new PayoutResource($payout))->toArray($request));

        return $this->apiSuccess('Payouts retrieved', ['payouts' => $payouts]);
    }
}
