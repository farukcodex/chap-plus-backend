<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rider\DeliveryIndexRequest;
use App\Http\Requests\Rider\UpdateDeliveryStatusRequest;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Notifications\Customer\OrderStatusUpdatedNotification;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class DeliveryController extends Controller
{
    use ApiResponseTrait;

    public function index(DeliveryIndexRequest $request): JsonResponse
    {
        $riderId = $request->user()->id;
        $filter = $request->query('filter', 'all'); // available, active, or completed
        $perPage = $request->query('per_page', 15);

        $query = Order::with([
            'address',
            'user:id,name',
            'merchantProfile' => function ($q) {
                $q->select('id', 'business_name', 'address', 'city', 'phone_number', 'latitude', 'longitude', 'currency');
            },
            'items.product.images',
            'items.product.category.parent',
            'items.variant'
        ]);

        if ($filter === 'ready_for_pickup') {
            // Global: ready for pickup, no rider assigned
            $query->where('status', 'ready_for_pickup')->whereNull('rider_id');
        } elseif ($filter === 'accepted') {
            // Personal: accepted by this rider
            $query->where('rider_id', $riderId)->where('status', 'accepted');
        } elseif ($filter === 'picked_up') {
            // Personal: picked up by this rider
            $query->where('rider_id', $riderId)->where('status', 'picked_up');
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
            $query->where('rider_id', $riderId)->whereIn('status', ['ready_for_pickup', 'accepted', 'picked_up', 'on_the_way']);
        }

        if ($type = $request->input('type')) {
            $query->whereHas('items.product.category', function ($q) use ($type) {
                $q->where('type', $type)
                  ->orWhereHas('parent', function ($pq) use ($type) {
                      $pq->where('type', $type);
                  });
            });
        }

        $commissionPercent = (float) (\App\Models\PlatformSetting::where('key', 'rider_commission_percent')->value('value') ?? 0.0);
        $orders = $query->latest()->paginate($perPage);
        $orders->through(fn ($order) => $this->formatOrder($order, $commissionPercent));

        return $this->apiSuccess('Deliveries retrieved', ['orders' => $orders]);
    }

    public function show(string $id, Request $request): JsonResponse
    {
        $order = Order::with([
            'merchantProfile',
            'user',
            'items.product.images',
            'items.product.category.parent',
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

    public function updateStatus(string $id, UpdateDeliveryStatusRequest $request): JsonResponse
    {
        $status = $request->status;
        $riderId = $request->user()->id;

        // 1. Accept Order
        if ($status === 'accepted') {
            $order = Order::where('status', 'ready_for_pickup')->whereNull('rider_id')->find($id);

            if (!$order) {
                $existing = Order::find($id);
                if (!$existing) {
                    return $this->apiError('Order not found', 404, ['code' => 'ORDER_NOT_FOUND']);
                }
                return $this->apiError('Order is no longer available', 400, ['code' => 'ORDER_UNAVAILABLE']);
            }

            $order->update([
                'rider_id' => $riderId,
                'status'   => 'accepted',
            ]);
            $order->load(['merchantProfile', 'user', 'rider', 'items.product.images', 'items.product.category.parent', 'items.variant', 'address']);

            try {
                $order->user?->notify(new OrderStatusUpdatedNotification($order, 'accepted'));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Customer notification error (accepted): ' . $e->getMessage());
            }

            return $this->apiSuccess('Order accepted successfully', ['order' => $this->formatOrder($order)]);
        }

        // 2. Pickup Order
        if ($status === 'picked_up') {
            $order = Order::where('rider_id', $riderId)->whereIn('status', ['accepted', 'ready_for_pickup'])->find($id);

            if (!$order) {
                return $this->apiError('Invalid order or status for pickup', 400, ['code' => 'INVALID_ORDER_STATUS']);
            }

            $order->update(['status' => 'picked_up']);
            $order->load(['merchantProfile', 'user', 'rider', 'items.product.images', 'items.product.category.parent', 'items.variant', 'address']);

            try {
                $order->user?->notify(new OrderStatusUpdatedNotification($order, 'picked_up'));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Customer notification error (picked_up): ' . $e->getMessage());
            }

            return $this->apiSuccess('Order picked up successfully', ['order' => $this->formatOrder($order)]);
        }

        // 3. Mark On The Way
        if ($status === 'on_the_way') {
            $order = Order::where('rider_id', $riderId)->where('status', 'picked_up')->find($id);

            if (!$order) {
                return $this->apiError('Invalid order or status for on the way', 400, ['code' => 'INVALID_ORDER_STATUS']);
            }

            $order->update(['status' => 'on_the_way']);
            $order->load(['merchantProfile', 'user', 'rider', 'items.product.images', 'items.product.category.parent', 'items.variant', 'address']);

            try {
                $order->user?->notify(new OrderStatusUpdatedNotification($order, 'on_the_way'));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Customer notification error (on_the_way): ' . $e->getMessage());
            }

            return $this->apiSuccess('Order is now on the way', ['order' => $this->formatOrder($order)]);
        }

        // 4. Complete Delivery
        if ($status === 'delivered') {
            $order = Order::with('merchantProfile')->where('rider_id', $riderId)->whereIn('status', ['picked_up', 'on_the_way'])->find($id);

            if (!$order) {
                return $this->apiError('Invalid order or status for delivery', 400, ['code' => 'INVALID_ORDER_STATUS']);
            }

            if ($order->delivery_otp !== $request->otp) {
                return $this->apiError('Invalid Delivery PIN', 400, ['code' => 'INVALID_OTP']);
            }

            $riderEarnings = \Illuminate\Support\Facades\DB::transaction(function () use ($order) {
                $order->update(['status' => 'delivered']);

                // 1. Fetch Commission Settings
                $merchantCommissionPercent = (float) (\App\Models\PlatformSetting::where('key', 'merchant_commission_percent')->value('value') ?? 10.00);
                $riderCommissionPercent = (float) (\App\Models\PlatformSetting::where('key', 'rider_commission_percent')->value('value') ?? 0.00);

                $totalAmount = (float) $order->total_amount;
                $deliveryFee = (float) ($order->delivery_fee ?? 0);

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
                if ($order->merchantProfile && $order->merchantProfile->user_id) {
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
                }

                // 4. Rider Wallet
                if ($order->rider_id) {
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
                }

                return $riderEarnings;
            });

            $order->load(['merchantProfile', 'user', 'rider', 'items.product.images', 'items.product.category.parent', 'items.variant', 'address']);

            // Notify Rider (Database Drawer + Expo Push)
            try {
                $request->user()->notify(new \App\Notifications\Rider\DeliveryCompletedNotification($order, (float) $riderEarnings));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Failed sending DeliveryCompletedNotification: ' . $e->getMessage());
            }

            // Notify Customer (Database Inbox + Expo Push + Delivery Email)
            try {
                $order->user?->notify(new OrderStatusUpdatedNotification($order, 'delivered'));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Customer notification error (delivered): ' . $e->getMessage());
            }

            return $this->apiSuccess('Delivery confirmed successfully and wallets updated!', ['order' => $this->formatOrder($order)]);
        }

        return $this->apiError('Invalid status transition', 400);
    }

    public function updateLocation(string $id, Request $request): JsonResponse
    {
        $request->validate([
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
            'heading'   => 'sometimes|nullable|numeric',
        ]);

        $order = Order::where('rider_id', $request->user()->id)->whereIn('status', ['picked_up', 'on_the_way'])->find($id);

        if (!$order) {
            return $this->apiError('Order is not currently active for tracking', 400, ['code' => 'INVALID_ORDER_STATUS']);
        }

        $heading = $request->filled('heading') ? (float) $request->heading : null;

        // Broadcast the location immediately via WebSockets
        event(new \App\Events\RiderLocationUpdated($order->id, (float) $request->latitude, (float) $request->longitude, $heading));

        // Save only the latest location to cache so the customer can fetch it on load without waiting for the next ping
        \Illuminate\Support\Facades\Cache::put(
            'order_' . $order->id . '_location',
            [
                'latitude'   => (float) $request->latitude,
                'longitude'  => (float) $request->longitude,
                'heading'    => $heading,
                'updated_at' => now()->toIso8601String(),
            ],
            3600 // Cache for 1 hour
        );

        return $this->apiSuccess('Location updated');
    }

    /**
     * Format the order for optimal mobile rider experience.
     */
    private function formatOrder(Order $order, ?float $commissionPercent = null): array
    {
        $currency = $order->currency ?? $order->merchantProfile->currency ?? 'USD';

        if (is_null($commissionPercent)) {
            $commissionPercent = (float) (\App\Models\PlatformSetting::where('key', 'rider_commission_percent')->value('value') ?? 0.0);
        }

        $deliveryFee = (float) ($order->delivery_fee ?? 0);
        $commissionAmount = round($deliveryFee * ($commissionPercent / 100), 2);
        $riderEarnings = round($deliveryFee - $commissionAmount, 2);

        $isAccepted = !is_null($order->rider_id) || in_array($order->status, ['accepted', 'picked_up', 'on_the_way', 'delivered']);
        $isPickedUp = in_array($order->status, ['picked_up', 'on_the_way', 'delivered']);
        $isOnTheWay = in_array($order->status, ['on_the_way', 'delivered']);
        $isDelivered = $order->status === 'delivered';

        $firstCategory = $order->items->first()?->product?->category;
        $orderRootCategory = $firstCategory;
        while ($orderRootCategory && $orderRootCategory->parent) {
            $orderRootCategory = $orderRootCategory->parent;
        }

        return [
            'id' => $order->id,
            'order_number' => $order->order_number ?? ('#ORD-' . str_pad($order->id, 5, '0', STR_PAD_LEFT)),
            'category' => $orderRootCategory?->name ?? null,
            'status' => $order->status,
            'currency' => $currency,
            'delivery_timeline' => [
                'accepted' => (bool) $isAccepted,
                'picked_up' => (bool) $isPickedUp,
                'on_the_way' => (bool) $isOnTheWay,
                'delivered' => (bool) $isDelivered,
            ],
            'delivery_fee' => $deliveryFee,
            'rider_earnings' => $riderEarnings,
            'commission_amount' => $commissionAmount,
            'commission_percent' => $commissionPercent,
            'distance_km' => $order->distance_km !== null ? (float) $order->distance_km : null,
            'duration_minute' => $order->duration_minute !== null ? (int) $order->duration_minute : null,
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
                'latitude' => $order->address?->latitude ? (float) $order->address->latitude : null,
                'longitude' => $order->address?->longitude ? (float) $order->address->longitude : null,
            ],
            'items' => $order->items->map(function ($item) use ($currency) {
                $category = $item->product?->category;
                $rootCategory = $category;
                while ($rootCategory && $rootCategory->parent) {
                    $rootCategory = $rootCategory->parent;
                }

                $hasSubCategory = $category && $rootCategory && $category->id !== $rootCategory->id;

                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'name' => $item->product->name ?? 'Unknown',
                    'category' => $rootCategory?->name ?? $category?->name ?? null,
                    'sub_category' => $hasSubCategory ? $category->name : null,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (float) $item->price_at_time_of_purchase,
                    'total_price' => round((float) $item->price_at_time_of_purchase * $item->quantity, 2),
                    'currency' => $currency,
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
