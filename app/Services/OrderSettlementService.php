<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderSettlementService
{
    /**
     * Calculate financial commission and earnings split for an order.
     */
    public function calculateSplits(Order $order): array
    {
        $merchantRate = PlatformSetting::getCommissionRate($order->type ?: 'ecommerce');
        $riderRate = PlatformSetting::getCommissionRate('rider');

        $totalAmount = (float) $order->total_amount;
        $deliveryFee = (float) ($order->delivery_fee ?? 0);

        $adminMerchantCut = round($totalAmount * ($merchantRate / 100), 2);
        $merchantEarnings = round($totalAmount - $adminMerchantCut, 2);

        $adminRiderCut = round($deliveryFee * ($riderRate / 100), 2);
        $riderEarnings = round($deliveryFee - $adminRiderCut, 2);

        $totalAdminCommission = round($adminMerchantCut + $adminRiderCut, 2);

        return [
            'merchant_commission_rate' => $merchantRate,
            'admin_merchant_cut'       => $adminMerchantCut,
            'merchant_earnings'        => $merchantEarnings,
            'rider_commission_rate'    => $riderRate,
            'admin_rider_cut'          => $adminRiderCut,
            'rider_earnings'           => $riderEarnings,
            'admin_commission'         => $totalAdminCommission,
            'order_price'              => round($totalAmount + $deliveryFee, 2),
        ];
    }

    /**
     * Settle order financials atomically:
     * 1. Persist snapshot fields directly on the orders table.
     * 2. Credit Admin, Merchant, and Rider wallets.
     * 3. Log double-entry transactions in wallet_transactions.
     * 4. Mark commission_settled_at timestamp to prevent duplicate payout.
     */
    public function settle(Order $order): bool
    {
        // 1. Guard against double settlement
        if ($order->commission_settled_at !== null) {
            Log::info("Order #{$order->id} already settled at {$order->commission_settled_at}. Skipping.");
            return false;
        }

        return DB::transaction(function () use ($order) {
            /** @var Order|null $lockedOrder */
            $lockedOrder = Order::with('merchantProfile.user')->where('id', $order->id)->lockForUpdate()->first();

            if (!$lockedOrder || $lockedOrder->commission_settled_at !== null) {
                return false;
            }

            // 2. Compute splits
            $splits = $this->calculateSplits($lockedOrder);

            // 3. Persist frozen snapshot on order record
            $lockedOrder->update([
                'merchant_commission_rate' => $splits['merchant_commission_rate'],
                'admin_commission'         => $splits['admin_commission'],
                'merchant_earnings'        => $splits['merchant_earnings'],
                'rider_commission_rate'    => $splits['rider_commission_rate'],
                'rider_earnings'           => $splits['rider_earnings'],
                'commission_settled_at'    => now(),
            ]);

            // 4. Credit Admin Wallet
            $adminUser = User::role('ADMIN')->first();
            if ($adminUser && $splits['admin_commission'] > 0) {
                $adminWallet = Wallet::firstOrCreate(['user_id' => $adminUser->id]);
                $adminWallet->increment('balance', $splits['admin_commission']);
                WalletTransaction::create([
                    'wallet_id'      => $adminWallet->id,
                    'type'           => 'credit',
                    'amount'         => $splits['admin_commission'],
                    'reference_type' => Order::class,
                    'reference_id'   => $lockedOrder->id,
                    'description'    => "Platform commission for Order #{$lockedOrder->id}",
                ]);
            }

            // 5. Credit Merchant Wallet
            $merchantUserId = $lockedOrder->merchantProfile?->user_id;
            if ($merchantUserId && $splits['merchant_earnings'] > 0) {
                $merchantWallet = Wallet::firstOrCreate(['user_id' => $merchantUserId]);
                $merchantWallet->increment('balance', $splits['merchant_earnings']);
                WalletTransaction::create([
                    'wallet_id'      => $merchantWallet->id,
                    'type'           => 'credit',
                    'amount'         => $splits['merchant_earnings'],
                    'reference_type' => Order::class,
                    'reference_id'   => $lockedOrder->id,
                    'description'    => "Earnings for Order #{$lockedOrder->id}",
                ]);
            }

            // 6. Credit Rider Wallet
            if ($lockedOrder->rider_id && $splits['rider_earnings'] > 0) {
                $riderWallet = Wallet::firstOrCreate(['user_id' => $lockedOrder->rider_id]);
                $riderWallet->increment('balance', $splits['rider_earnings']);
                WalletTransaction::create([
                    'wallet_id'      => $riderWallet->id,
                    'type'           => 'credit',
                    'amount'         => $splits['rider_earnings'],
                    'reference_type' => Order::class,
                    'reference_id'   => $lockedOrder->id,
                    'description'    => "Delivery fee for Order #{$lockedOrder->id}",
                ]);
            }

            return true;
        });
    }
}
