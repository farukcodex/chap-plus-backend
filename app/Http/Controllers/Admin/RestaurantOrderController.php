<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RestaurantOrderController extends Controller
{
    use ApiResponseTrait;

    /**
     * List all restaurant food orders with filtering, search, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::restaurant()->with([
            'user:id,name,email,profile_photo_path',
            'user.userProfile:id,user_id,phone_number',
            'merchantProfile:id,user_id,business_name,phone_number,address,city,country,currency,profile_image_path',
            'merchantProfile.user:id,name,email',
            'rider:id,name,email,profile_photo_path',
            'rider.riderProfile:id,user_id,phone_number',
            'address:id,title,address_text,phone_number,latitude,longitude',
            'items.product:id,name,base_price',
            'items.variant:id,attribute_name,attribute_value',
        ]);

        // Search by order number, customer name/email, restaurant name, or rider name
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                  ->orWhereHas('merchantProfile', fn ($mq) => $mq->where('business_name', 'like', "%{$search}%"))
                  ->orWhereHas('rider', fn ($rq) => $rq->where('name', 'like', "%{$search}%"));
            });
        }

        // Filter by order status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $perPage = max(1, (int) $request->input('per_page', 15));
        $orders = $query->latest()->paginate($perPage);

        $orders->through(fn (Order $order) => $this->formatOrderListItem($order));

        return $this->apiSuccess('Restaurant orders retrieved successfully', $orders);
    }

    /**
     * Get detailed restaurant food order information.
     */
    public function show(string $id): JsonResponse
    {
        $order = Order::restaurant()->with([
            'user:id,name,email,profile_photo_path',
            'user.userProfile',
            'merchantProfile.user:id,name,email',
            'merchantProfile',
            'rider:id,name,email,profile_photo_path',
            'rider.riderProfile',
            'address',
            'items.product.images',
            'items.variant',
        ])->find($id);

        if (!$order) {
            return $this->apiError('Restaurant order not found', 404);
        }

        return $this->apiSuccess('Restaurant order details retrieved successfully', [
            'order' => $this->formatOrderDetail($order)
        ]);
    }

    /**
     * Update food order status by Admin.
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:pending_payment,paid,processing,ready_for_pickup,accepted,picked_up,on_the_way,delivered,cancelled',
        ]);

        $order = Order::restaurant()->find($id);

        if (!$order) {
            return $this->apiError('Restaurant order not found', 404);
        }

        $order->update(['status' => $validated['status']]);

        if ($validated['status'] === 'delivered') {
            app(\App\Services\OrderSettlementService::class)->settle($order);
            $order->refresh();
        }

        return $this->apiSuccess('Restaurant order status updated successfully', [
            'order' => [
                'id'           => $order->id,
                'order_number' => $order->order_number,
                'status'       => $order->status,
                'updated_at'   => $order->updated_at?->toIso8601String(),
            ]
        ]);
    }

    /**
     * Format an order item for table listing.
     */
    private function formatOrderListItem(Order $order): array
    {
        $itemsCount = $order->items->sum('quantity') ?: $order->items->count();
        $foodPrice = (float) $order->total_amount;
        $deliveryFee = (float) ($order->delivery_fee ?? 0);
        $totalAmount = round($foodPrice + $deliveryFee, 2);
        $currency = $order->merchantProfile?->currency ?? 'KES';

        // Food items preview summary (e.g. "Burger x 2, Fries x 1")
        $foodSummary = $order->items->map(function ($item) {
            $name = $item->product?->name ?? 'Food Item';
            return "{$name} x {$item->quantity}";
        })->implode(', ');

        return [
            'id'                   => $order->id,
            'order_number'         => $order->order_number,
            'type'                 => 'restaurant',
            'customer'             => [
                'id'                => $order->user?->id,
                'name'              => $order->user?->name ?? 'N/A',
                'email'             => $order->user?->email,
                'phone'             => $order->user?->userProfile?->phone_number ?? 'N/A',
                'profile_photo_url' => $order->user?->profile_photo_url,
            ],
            'restaurant'           => [
                'id'            => $order->merchantProfile?->id,
                'name'          => $order->merchantProfile?->user?->name ?? 'N/A',
                'email'         => $order->merchantProfile?->user?->email,
                'business_name' => $order->merchantProfile?->business_name ?? 'N/A',
                'phone_number'  => $order->merchantProfile?->phone_number,
                'address'       => $order->merchantProfile?->address,
                'city'          => $order->merchantProfile?->city,
                'country'       => $order->merchantProfile?->country,
                'profile_image' => $order->merchantProfile?->profile_image_url,
            ],
            'rider'                => $order->rider ? [
                'id'                => $order->rider->id,
                'name'              => $order->rider->name,
                'email'             => $order->rider->email,
                'phone'             => $order->rider->riderProfile?->phone_number ?? 'N/A',
                'profile_photo_url' => $order->rider->profile_photo_url,
            ] : null,
            'delivery_address'     => [
                'title'        => $order->address?->title ?? 'Delivery Address',
                'address'      => $order->address?->address_text ?? 'N/A',
                'phone_number' => $order->address?->phone_number,
            ],
            'items_count'          => (int) $itemsCount,
            'food_items_summary'   => $foodSummary ?: 'No items',
            'food_price'           => $foodPrice,
            'delivery_fee'         => $deliveryFee,
            'total_amount'         => $totalAmount,
            'currency'             => $currency,
            'payment_method'       => $order->payment_method,
            'mpesa_receipt_number' => $order->mpesa_receipt_number,
            'status'               => $order->status,
            'distance_km'          => $order->distance_km !== null ? (float) $order->distance_km : null,
            'duration_minute'      => $order->duration_minute !== null ? (int) $order->duration_minute : null,
            'created_at'           => $order->created_at?->toIso8601String(),
        ];
    }

    /**
     * Format an order item with complete detail modal data.
     */
    private function formatOrderDetail(Order $order): array
    {
        $base = $this->formatOrderListItem($order);

        $base['delivery_otp'] = $order->delivery_otp;
        $base['cancellation_reason'] = $order->cancellation_reason;
        $base['rating'] = $order->rating;
        $base['review_comment'] = $order->review_comment;

        // Live location if out for delivery
        $base['live_location'] = null;
        if (in_array($order->status, ['picked_up', 'on_the_way'])) {
            $base['live_location'] = Cache::get('order_' . $order->id . '_location');
        }

        $base['items'] = $order->items->map(function ($item) {
            $product = $item->product;
            $unitPrice = (float) ($item->price_at_time_of_purchase ?? $item->unit_price ?? 0);
            $totalPrice = round($unitPrice * $item->quantity, 2);

            return [
                'id'            => $item->id,
                'product_id'    => $item->product_id,
                'product_name'  => $product?->name ?? 'Food Item',
                'product_image' => $product?->images->first()?->image_url,
                'variant'       => $item->variant ? [
                    'attribute_name'  => $item->variant->attribute_name,
                    'attribute_value' => $item->variant->attribute_value,
                ] : null,
                'unit_price'    => $unitPrice,
                'quantity'      => (int) $item->quantity,
                'total_price'   => $totalPrice,
            ];
        });

        return $base;
    }
}
