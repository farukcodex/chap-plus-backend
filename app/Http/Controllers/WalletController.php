<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\PayoutRequest;
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
        $request->validate([
            'amount' => 'required|numeric|min:100',
            'mpesa_number' => 'required|string',
        ]);

        $wallet = Wallet::firstOrCreate(['user_id' => $request->user()->id]);

        if ($wallet->balance < $request->amount) {
            return $this->apiError('Insufficient balance for payout', 400);
        }

        // Deduct from wallet and create payout request in a transaction
        \Illuminate\Support\Facades\DB::transaction(function () use ($wallet, $request) {
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

        return $this->apiSuccess('Payout request submitted successfully!');
    }

    public function payouts(Request $request): JsonResponse
    {
        $payouts = PayoutRequest::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        return $this->apiSuccess('Payouts retrieved', $payouts);
    }
}
