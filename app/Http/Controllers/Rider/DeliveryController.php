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
        $request->validate([
            'type' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    if (!in_array(strtolower($value), ['ecommerce', 'restaurant'])) {
                        $fail('The type must be either ecommerce or restaurant.');
                    }
                },
            ],
        ]);

        $riderId = $request->user()->id;
        $filter = $request->query('filter', 'all'); // available, active, or completed
        $perPage = $request->query('per_page', 15);

        $query = Order::with([
            'address',
            'user:id,name',
            'merchantProfile' => function ($q) {
                $q->select('id', 'business_name', 'address', 'city', 'phone_number', 'latitude', 'longitude');
            },
            'items.product.images',
            'items.variant'
        ]);

        if ($filter === 'ready_for_pickup') {
            // Global: ready for pickup, no rider assigned
            $query->where('status', 'ready_for_pickup')->whereNull('rider_id');
        } elseif ($filter === 'on_the_way') {
            // Personal: currently being delivered by this rider
            $query->where('rider_id', $riderId)->where('status', 'on_the_way');
        } elseif ($filter === 'delivered') {
            // Personal: delivered by this rider
            $query->where('rider_id', $riderId)->where('status', 'delivered');
        } elseif ($filter === 'all') {
            // Get all orders assigned to this rider
            $query->where('rider_id', $riderId);
        } else {
            // Personal: active (assigned to this rider but not yet delivered)
            $query->where('rider_id', $riderId)->whereIn('status', ['ready_for_pickup', 'on_the_way']);
        }

        if ($type = $request->query('type')) {
            $normalizedType = strtolower($type);
            $query->whereHas('items.product.category', function ($q) use ($normalizedType) {
                $q->where('type', $normalizedType)
                  ->orWhereHas('parent', function ($pq) use ($normalizedType) {
                      $pq->where('type', $normalizedType);
                  });
            });
        }

        $orders = $query->latest()->paginate($perPage);
        $orders->through(fn ($order) => $this->formatOrder($order));

        return $this->apiSuccess('Deliveries retrieved', ['orders' => $orders]);
    }

    public function show(string $id, Request $request): JsonResponse
    {
        $order = Order::with([
            'merchantProfile',
            'user',
            'items.product.images',
            'items.variant',
            'address'
        ])->find($id);

        if (!$order) {
            return $this->apiError('Order not found', 404, ['code' => 'ORDER_NOT_FOUND']);
        }

        // Authorization check: A rider can only view an order if they own it, OR if it's available for anyone to accept.
        $isAvailable = is_null($order->rider_id) && $order->status === 'ready_for_pickup';
        $isOwner = $order->rider_id === $request->user()->id;

        if (!$isAvailable && !$isOwner) {
            return $this->apiError('Unauthorized to view this order', 403, ['code' => 'UNAUTHORIZED_ORDER_ACCESS']);
        }

        return $this->apiSuccess('Delivery details retrieved', ['order' => $this->formatOrder($order)]);
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
        $order->load(['merchantProfile', 'user', 'items.product.images', 'items.variant', 'address']);

        return $this->apiSuccess('Order accepted successfully', ['order' => $this->formatOrder($order)]);
    }

    public function pickup(string $id, Request $request): JsonResponse
    {
        $order = Order::where('rider_id', $request->user()->id)->where('status', 'ready_for_pickup')->find($id);

        if (!$order) {
            return $this->apiError('Invalid order or status for pickup', 400, ['code' => 'INVALID_ORDER_STATUS']);
        }

        $order->update(['status' => 'on_the_way']);
        $order->load(['merchantProfile', 'user', 'items.product.images', 'items.variant', 'address']);

        return $this->apiSuccess('Order picked up successfully', ['order' => $this->formatOrder($order)]);
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
            $riderCommissionPercent = \App\Models\PlatformSetting::where('key', 'rider_commission_percent')->value('value') ?? 0.00;

            $totalAmount = $order->total_amount;
            $deliveryFee = $order->delivery_fee ?? 0;

            // Calculate splits
            $adminMerchantCommission = $totalAmount * ($merchantCommissionPercent / 100);
            $merchantEarnings = $totalAmount - $adminMerchantCommission;

            $adminRiderCommission = $deliveryFee * ($riderCommissionPercent / 100);
            $riderEarnings = $deliveryFee - $adminRiderCommission;

            $totalAdminCommission = $adminMerchantCommission + $adminRiderCommission;

            // 2. Admin Wallet
            $adminUser = \App\Models\User::role('ADMIN')->first();
            if ($adminUser) {
                $adminWallet = \App\Models\Wallet::firstOrCreate(['user_id' => $adminUser->id]);
                $adminWallet->increment('balance', $totalAdminCommission);
                \App\Models\WalletTransaction::create([
                    'wallet_id' => $adminWallet->id,
                    'type' => 'credit',
                    'amount' => $totalAdminCommission,
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

        $order->load(['merchantProfile', 'user', 'items.product.images', 'items.variant', 'address']);

        return $this->apiSuccess('Delivery confirmed successfully and wallets updated!', ['order' => $this->formatOrder($order)]);
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
            'order_' . $order->id . '_location',
            ['latitude' => $request->latitude, 'longitude' => $request->longitude],
            3600 // Cache for 1 hour
        );

        return $this->apiSuccess('Location updated');
    }

    /**
     * Format the order for optimal mobile rider experience.
     */
    private function formatOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'status' => $order->status,
            'delivery_fee' => (float) $order->delivery_fee,
            'total_amount' => (float) $order->total_amount,
            'payment_method' => $order->payment_method,
            'total_items' => (int) $order->items->sum('quantity'),
            'pickup' => [
                'business_name' => $order->merchantProfile->business_name ?? null,
                'address' => $order->merchantProfile->address ?? null,
                'city' => $order->merchantProfile->city ?? null,
                'phone_number' => $order->merchantProfile->phone_number ?? null,
                'latitude' => $order->merchantProfile->latitude ? (float) $order->merchantProfile->latitude : null,
                'longitude' => $order->merchantProfile->longitude ? (float) $order->merchantProfile->longitude : null,
            ],
            'dropoff' => [
                'customer_name' => $order->user->name ?? null,
                'title' => $order->address->title ?? null,
                'address_text' => $order->address->address_text ?? null,
                'phone_number' => $order->address->phone_number ?? null,
                'latitude' => $order->address->latitude ? (float) $order->address->latitude : null,
                'longitude' => $order->address->longitude ? (float) $order->address->longitude : null,
            ],
            'items' => $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'name' => $item->product->name ?? 'Unknown',
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (float) $item->price_at_time_of_purchase,
                    'total_price' => round((float) $item->price_at_time_of_purchase * $item->quantity, 2),
                    'variant' => $item->variant ? [
                        'id' => $item->variant->id,
                        'name' => $item->variant->name,
                    ] : null,
                    'image' => $item->product?->images->first()?->image_url ?? null,
                ];
            })->values(),
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }
}
