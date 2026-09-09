<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Resources\Customer\RestaurantOrderResource;
use App\Models\Order;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class RestaurantOrderController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $filter = $request->query('filter', 'active'); // active, completed, cancelled

        $query = Order::restaurant()
            ->with(['items.product.images', 'items.variant', 'merchantProfile', 'address', 'rider.riderProfile'])
            ->where('user_id', $request->user()->id);

        if ($filter === 'active') {
            $query->whereIn('status', ['pending_payment', 'failed', 'paid', 'processing', 'ready_for_pickup', 'accepted', 'picked_up', 'on_the_way']);
        } elseif ($filter === 'completed') {
            $query->where('status', 'delivered');
        } elseif ($filter === 'cancelled') {
            $query->whereIn('status', ['cancelled']);
        }

        $orders = $query->latest()->paginate(10);
        $orders->through(fn($order) => (new RestaurantOrderResource($order))->toArray($request));

        return $this->apiSuccess('Restaurant orders retrieved successfully', ['orders' => $orders]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $order = Order::restaurant()
            ->with(['items.product.images', 'items.variant', 'merchantProfile', 'rider.riderProfile', 'address'])
            ->where('user_id', $request->user()->id)
            ->find($id);

        if (!$order) {
            return $this->apiError('Food order not found', 404, ['code' => 'ORDER_NOT_FOUND']);
        }

        $orderData = (new RestaurantOrderResource($order))->toArray($request);
        $orderData['live_location'] = null;

        if (in_array($order->status, ['picked_up', 'on_the_way'])) {
            $orderData['live_location'] = Cache::get('order_' . $order->id . '_location');
        }

        return $this->apiSuccess('Food order details retrieved', ['order' => $orderData]);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:255'
        ]);

        $order = Order::restaurant()->where('user_id', $request->user()->id)->find($id);

        if (!$order) {
            return $this->apiError('Food order not found', 404, ['code' => 'ORDER_NOT_FOUND']);
        }

        if (!in_array($order->status, ['pending_payment', 'paid'])) {
            return $this->apiError("You cannot cancel a food order that is already {$order->status}", 400, ['code' => 'INVALID_ORDER_STATUS']);
        }

        $order->update([
            'status' => 'cancelled',
            'cancellation_reason' => $validated['reason']
        ]);

        $order->load(['merchantProfile', 'address', 'items.product.images', 'items.variant']);

        return $this->apiSuccess('Food order cancelled successfully', [
            'order' => new RestaurantOrderResource($order)
        ]);
    }

    public function review(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'review_comment' => 'nullable|string|max:1000'
        ]);

        $order = Order::restaurant()->where('user_id', $request->user()->id)->find($id);

        if (!$order) {
            return $this->apiError('Food order not found', 404, ['code' => 'ORDER_NOT_FOUND']);
        }

        if ($order->status !== 'delivered') {
            return $this->apiError('You can only review delivered food orders', 400, ['code' => 'INVALID_ORDER_STATUS']);
        }

        $order->update([
            'rating' => $validated['rating'],
            'review_comment' => $validated['review_comment'] ?? null
        ]);

        $order->load(['merchantProfile', 'address', 'items.product.images', 'items.variant']);

        return $this->apiSuccess('Food review submitted successfully', [
            'order' => new RestaurantOrderResource($order)
        ]);
    }

    public function tracking(Request $request, string $id): JsonResponse
    {
        $order = Order::restaurant()
            ->with(['rider.riderProfile', 'address', 'merchantProfile', 'items', 'user.userProfile'])
            ->where('user_id', $request->user()->id)
            ->find($id);

        if (!$order) {
            return $this->apiError('Food order not found', 404, ['code' => 'ORDER_NOT_FOUND']);
        }

        // 1. Fetch cached live location if active
        $cachedLocation = Cache::get('order_' . $order->id . '_location');

        // Rider location coordinates
        $riderCoordinates = null;
        if ($cachedLocation && is_array($cachedLocation)) {
            $riderCoordinates = [
                'latitude'     => isset($cachedLocation['latitude']) ? (float) $cachedLocation['latitude'] : null,
                'longitude'    => isset($cachedLocation['longitude']) ? (float) $cachedLocation['longitude'] : null,
                'heading'      => isset($cachedLocation['heading']) ? (float) $cachedLocation['heading'] : null,
                'last_updated' => $cachedLocation['updated_at'] ?? null,
            ];
        } elseif ($order->rider?->riderProfile?->latitude && $order->rider?->riderProfile?->longitude) {
            $riderCoordinates = [
                'latitude'     => (float) $order->rider->riderProfile->latitude,
                'longitude'    => (float) $order->rider->riderProfile->longitude,
                'heading'      => null,
                'last_updated' => null,
            ];
        }

        // Customer coordinates
        $customerCoordinates = [
            'title'     => $order->address?->title ?? 'Your location',
            'address'   => $order->address?->address_text ?? $order->user?->userProfile?->address ?? '',
            'latitude'  => $order->address?->latitude !== null ? (float) $order->address->latitude : ($order->user?->userProfile?->latitude !== null ? (float) $order->user->userProfile->latitude : null),
            'longitude' => $order->address?->longitude !== null ? (float) $order->address->longitude : ($order->user?->userProfile?->longitude !== null ? (float) $order->user->userProfile->longitude : null),
        ];

        // Store / Restaurant coordinates
        $storeCoordinates = [
            'name'      => $order->merchantProfile?->business_name ?? 'Restaurant',
            'address'   => $order->merchantProfile?->address ?? '',
            'latitude'  => $order->merchantProfile?->latitude !== null ? (float) $order->merchantProfile->latitude : null,
            'longitude' => $order->merchantProfile?->longitude !== null ? (float) $order->merchantProfile->longitude : null,
        ];

        // 2. Timeline steps matching mobile UI
        $isConfirmed = in_array($order->status, ['paid', 'processing', 'ready_for_pickup', 'accepted', 'picked_up', 'on_the_way', 'delivered']);
        $isPickedUp = in_array($order->status, ['picked_up', 'on_the_way', 'delivered']);
        $isOnTheWay = in_array($order->status, ['on_the_way', 'delivered']);
        $isDelivered = $order->status === 'delivered';

        $deliveryTimeline = [
            [
                'key'    => 'order_confirmed',
                'title'  => 'Order Confirmed',
                'time'   => $order->created_at ? $order->created_at->format('g:i A') : 'Pending',
                'status' => $isConfirmed ? 'completed' : ($order->status === 'pending_payment' ? 'in_progress' : 'pending'),
            ],
            [
                'key'    => 'rider_picked',
                'title'  => 'Rider Picked',
                'time'   => $isPickedUp ? ($order->updated_at ? $order->updated_at->format('g:i A') : 'Completed') : ($isConfirmed && !$isPickedUp ? 'In progress' : 'Pending'),
                'status' => $isPickedUp ? 'completed' : ($isConfirmed ? 'in_progress' : 'pending'),
            ],
            [
                'key'    => 'on_the_way',
                'title'  => 'On the Way',
                'time'   => $isOnTheWay ? ($order->updated_at ? $order->updated_at->format('g:i A') : 'Completed') : ($isPickedUp ? 'In progress' : 'Pending'),
                'status' => $isOnTheWay ? 'completed' : ($isPickedUp ? 'in_progress' : 'pending'),
            ],
            [
                'key'    => 'nearby',
                'title'  => 'Nearby',
                'time'   => $isDelivered ? ($order->updated_at ? $order->updated_at->format('g:i A') : 'Completed') : ($order->status === 'on_the_way' ? 'In 15 min' : 'Pending'),
                'status' => $isDelivered ? 'completed' : ($order->status === 'on_the_way' ? 'in_progress' : 'pending'),
            ],
            [
                'key'    => 'delivered',
                'title'  => 'Delivered',
                'time'   => $isDelivered ? ($order->updated_at ? $order->updated_at->format('g:i A') : 'Completed') : 'Pending',
                'status' => $isDelivered ? 'completed' : 'pending',
            ],
        ];

        // Legacy boolean map for backward compatibility
        $legacyTimeline = [
            'order_confirmed'   => $isConfirmed,
            'kitchen_preparing' => in_array($order->status, ['processing', 'ready_for_pickup', 'accepted', 'picked_up', 'on_the_way', 'delivered']),
            'ready_for_pickup'  => in_array($order->status, ['ready_for_pickup', 'accepted', 'picked_up', 'on_the_way', 'delivered']),
            'rider_assigned'    => in_array($order->status, ['accepted', 'picked_up', 'on_the_way', 'delivered']),
            'picked_up'         => $isPickedUp,
            'on_the_way'        => $isOnTheWay,
            'delivered'         => $isDelivered,
        ];

        // 3. Order details summary
        $itemsCount = $order->items->sum('quantity') ?: $order->items->count();
        $productPrice = (float) $order->total_amount;
        $deliveryCharge = (float) ($order->delivery_fee ?? 0);
        $totalAmount = round($productPrice + $deliveryCharge, 2);
        $currency = $order->currency ?? $order->merchantProfile?->currency ?? 'USD';

        $orderDetails = [
            'id'              => $order->id,
            'order_number'    => $order->order_number,
            'status'          => $order->status,
            'delivery_otp'    => $order->delivery_otp,
            'items_count'     => (int) $itemsCount,
            'product_price'   => $productPrice,
            'delivery_charge' => $deliveryCharge,
            'total_amount'    => $totalAmount,
            'currency'        => $currency,
        ];

        // 4. Rider info
        $rider = $order->rider ? [
            'id'            => (int) $order->rider->id,
            'name'          => (string) $order->rider->name,
            'phone_number'  => (string) ($order->rider->phone ?? $order->rider->riderProfile?->phone_number ?? ''),
            'profile_photo' => $order->rider->profile_photo_url,
        ] : null;

        return $this->apiSuccess('Food order tracking info retrieved', [
            'order_details'     => $orderDetails,
            'coordinates'       => [
                'customer' => $customerCoordinates,
                'store'    => $storeCoordinates,
                'rider'    => $riderCoordinates,
            ],
            'rider'             => $rider,
            'delivery_timeline' => $deliveryTimeline,
            'timeline'          => $legacyTimeline,
            'realtime'          => [
                'channel' => 'private-order.' . $order->id,
                'event'   => 'RiderLocationUpdated',
            ],
            'status'            => $order->status,
            'delivery_otp'      => $order->delivery_otp,
        ]);
    }
}
