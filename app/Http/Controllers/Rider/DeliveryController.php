<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Order;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class DeliveryController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $riderId = $request->user()->id;
        $filter = $request->query('filter', 'all'); // available, active, or completed

        $query = Order::with(['merchantProfile' => function ($q) {
            $q->select('id', 'business_name', 'address', 'city');
        }]);

        if ($filter === 'available') {
            // Global: ready for pickup, no rider assigned
            $query->where('status', 'ready_for_pickup')->whereNull('rider_id');
        } elseif ($filter === 'all') {
            // Get all
            $query->where('rider_id', $riderId);
        } elseif ($filter === 'completed') {
            // Personal: delivered by this rider
            $query->where('rider_id', $riderId)->where('status', 'delivered');
        } else {
            // Personal: active (assigned to this rider but not yet delivered)
            $query->where('rider_id', $riderId)->whereIn('status', ['ready_for_pickup', 'on_the_way']);
        }

        $orders = $query->latest()->paginate(15);
        $orders->getCollection()->makeHidden('delivery_otp'); // Hide OTP from list view

        return $this->apiSuccess('Deliveries retrieved', ['orders' => $orders]);
    }

    public function show(string $id, Request $request): JsonResponse
    {
        $order = Order::with(['merchantProfile', 'user', 'items'])->find($id);

        if (!$order) {
            return $this->apiError('Order not found', 404, ['code' => 'ORDER_NOT_FOUND']);
        }
        
        // Authorization check: A rider can only view an order if they own it, OR if it's available for anyone to accept.
        $isAvailable = is_null($order->rider_id) && $order->status === 'ready_for_pickup';
        $isOwner = $order->rider_id === $request->user()->id;

        if (!$isAvailable && !$isOwner) {
            return $this->apiError('Unauthorized to view this order', 403, ['code' => 'UNAUTHORIZED_ORDER_ACCESS']);
        }

        $order->makeHidden('delivery_otp');

        return $this->apiSuccess('Delivery details retrieved', ['order' => $order]);
    }

    public function accept(string $id, Request $request): JsonResponse
    {
        $order = Order::where('status', 'ready_for_pickup')->whereNull('rider_id')->find($id);

        if (!$order) {
            return $this->apiError('Order not found', 404, ['code' => 'ORDER_NOT_FOUND']);
        }
        
        if ($order->status !== 'ready_for_pickup') {
            return $this->apiError('Order is no longer available', 400, ['code' => 'ORDER_UNAVAILABLE']);
        }

        $order->update([
            'rider_id' => $request->user()->id,
        ]);

        return $this->apiSuccess('Order accepted successfully', ['order' => $order]);
    }

    public function pickup(string $id, Request $request): JsonResponse
    {
        $order = Order::where('rider_id', $request->user()->id)->where('status', 'ready_for_pickup')->find($id);

        if (!$order) {
            return $this->apiError('Invalid order or status for pickup', 400, ['code' => 'INVALID_ORDER_STATUS']);
        }

        $order->update(['status' => 'on_the_way']);

        return $this->apiSuccess('Order picked up successfully', ['order' => $order]);
    }

    public function deliver(string $id, Request $request): JsonResponse
    {
        $request->validate(['otp' => 'required|string']);

        $order = Order::with('merchantProfile')->where('rider_id', $request->user()->id)->where('status', 'on_the_way')->find($id);

        if (!$order) {
            return $this->apiError('Invalid order or status for delivery', 400, ['code' => 'INVALID_ORDER_STATUS']);
        }

        if ($order->delivery_otp !== $request->otp) {
            return $this->apiError('Invalid Delivery PIN', 400, ['code' => 'INVALID_OTP']);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($order) {
            $order->update(['status' => 'delivered']);

            // 1. Fetch Commission Settings
            $merchantCommissionPercent = \App\Models\PlatformSetting::where('key', 'merchant_commission_percent')->value('value') ?? 10.00;
            
            $totalAmount = $order->total_amount;
            $deliveryFee = $order->delivery_fee ?? 0;

            $adminCommission = $totalAmount * ($merchantCommissionPercent / 100);
            $merchantEarnings = $totalAmount - $adminCommission;
            $riderEarnings = $deliveryFee;

            // 2. Admin Wallet
            $adminUser = \App\Models\User::role('ADMIN')->first();
            if ($adminUser) {
                $adminWallet = \App\Models\Wallet::firstOrCreate(['user_id' => $adminUser->id]);
                $adminWallet->increment('balance', $adminCommission);
                \App\Models\WalletTransaction::create([
                    'wallet_id' => $adminWallet->id,
                    'type' => 'credit',
                    'amount' => $adminCommission,
                    'reference_type' => \App\Models\Order::class,
                    'reference_id' => $order->id,
                    'description' => "Platform commission for Order #{$order->id}",
                ]);
            }

            // 3. Merchant Wallet
            $merchantWallet = \App\Models\Wallet::firstOrCreate(['user_id' => $order->merchantProfile->user_id]);
            $merchantWallet->increment('balance', $merchantEarnings);
            \App\Models\WalletTransaction::create([
                'wallet_id' => $merchantWallet->id,
                'type' => 'credit',
                'amount' => $merchantEarnings,
                'reference_type' => \App\Models\Order::class,
                'reference_id' => $order->id,
                'description' => "Earnings for Order #{$order->id}",
            ]);

            // 4. Rider Wallet
            $riderWallet = \App\Models\Wallet::firstOrCreate(['user_id' => $order->rider_id]);
            $riderWallet->increment('balance', $riderEarnings);
            \App\Models\WalletTransaction::create([
                'wallet_id' => $riderWallet->id,
                'type' => 'credit',
                'amount' => $riderEarnings,
                'reference_type' => \App\Models\Order::class,
                'reference_id' => $order->id,
                'description' => "Delivery fee for Order #{$order->id}",
            ]);
        });

        return $this->apiSuccess('Delivery confirmed successfully and wallets updated!', ['order' => $order]);
    }

    public function updateLocation(string $id, Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $order = Order::where('rider_id', $request->user()->id)->where('status', 'on_the_way')->find($id);

        if (!$order) {
            return $this->apiError('Order is not currently active for tracking', 400, ['code' => 'INVALID_ORDER_STATUS']);
        }

        // Broadcast the location immediately via WebSockets
        event(new \App\Events\RiderLocationUpdated($order->id, $request->latitude, $request->longitude));

        // Save only the latest location to cache so the customer can fetch it on load without waiting for the next ping
        \Illuminate\Support\Facades\Cache::put(
            'order_'.$order->id.'_location', 
            ['latitude' => $request->latitude, 'longitude' => $request->longitude], 
            3600 // Cache for 1 hour
        );

        return $this->apiSuccess('Location updated');
    }
}
