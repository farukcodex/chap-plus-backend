<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EcommerceOrderController extends Controller
{
    use ApiResponseTrait;

    /**
     * List all e-commerce orders with filtering, search, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::ecommerce()->with([
            'user:id,name,email,profile_photo_path',
            'user.userProfile:id,user_id,phone_number',
            'merchantProfile:id,user_id,business_name,phone_number,address,city,country,currency',
            'merchantProfile.user:id,name,email',
            'rider:id,name,email,profile_photo_path',
            'rider.riderProfile:id,user_id,phone_number',
            'address:id,title,address_text,latitude,longitude',
            'items.product:id,name,base_price',
            'items.variant:id,attribute_name,attribute_value',
        ]);

        // Search by order number, customer name/email, store name, or rider name
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

        return $this->apiSuccess('E-commerce orders retrieved successfully', $orders);
    }

    /**
     * Get detailed e-commerce order information.
     */
    public function show(string $id): JsonResponse
    {
        $order = Order::ecommerce()->with([
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
            return $this->apiError('E-commerce order not found', 404);
        }

        return $this->apiSuccess('E-commerce order details retrieved successfully', [
            'order' => $this->formatOrderDetail($order)
        ]);
    }

    /**
     * Update order status by Admin.
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:pending_payment,paid,processing,ready_for_pickup,accepted,picked_up,on_the_way,delivered,cancelled',
        ]);

        $order = Order::ecommerce()->find($id);

        if (!$order) {
            return $this->apiError('E-commerce order not found', 404);
        }

        $order->update(['status' => $validated['status']]);

        if ($validated['status'] === 'delivered') {
            app(\App\Services\OrderSettlementService::class)->settle($order);
            $order->refresh();
        }

        return $this->apiSuccess('Order status updated successfully', [
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
        $productPrice = (float) $order->total_amount;
        $deliveryFee = (float) ($order->delivery_fee ?? 0);
        $totalAmount = round($productPrice + $deliveryFee, 2);
        $currency = $order->currency ?? $order->merchantProfile?->currency ?? 'USD';

        return [
            'id'                   => $order->id,
            'order_number'         => $order->order_number,
            'customer'             => [
                'id'                => $order->user?->id,
                'name'              => $order->user?->name ?? 'N/A',
                'email'             => $order->user?->email,
                'phone'             => $order->user?->userProfile?->phone_number ?? 'N/A',
                'profile_photo_url' => $order->user?->profile_photo_url,
            ],
            'merchant'             => [
                'id'            => $order->merchantProfile?->id,
                'name'          => $order->merchantProfile?->user?->name ?? 'N/A',
                'email'         => $order->merchantProfile?->user?->email,
                'business_name' => $order->merchantProfile?->business_name ?? 'N/A',
                'city'          => $order->merchantProfile?->city,
                'country'       => $order->merchantProfile?->country,
            ],
            'rider'                => $order->rider ? [
                'id'                => $order->rider->id,
                'name'              => $order->rider->name,
                'email'             => $order->rider->email,
                'phone'             => $order->rider->riderProfile?->phone_number ?? 'N/A',
                'profile_photo_url' => $order->rider->profile_photo_url,
            ] : null,
            'delivery_address'     => [
                'title'   => $order->address?->title ?? 'Delivery Address',
                'address' => $order->address?->address_text ?? 'N/A',
            ],
            'items_count'          => (int) $itemsCount,
            'product_price'        => $productPrice,
            'delivery_fee'         => $deliveryFee,
            'total_amount'         => $totalAmount,
            'currency'             => $currency,
            'payment_method'       => $order->payment_method,
            'mpesa_receipt_number' => $order->mpesa_receipt_number,
            'status'               => $order->status,
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

        $base['items'] = $order->items->map(function ($item) {
            return [
                'id'            => $item->id,
                'product_id'    => $item->product_id,
                'product_name'  => $item->product?->name ?? 'Product',
                'product_image' => $item->product?->images->first()?->image_url,
                'variant'       => $item->variant ? [
                    'attribute_name'  => $item->variant->attribute_name,
                    'attribute_value' => $item->variant->attribute_value,
                ] : null,
                'unit_price'    => (float) ($item->price_at_time_of_purchase ?? $item->unit_price ?? 0),
                'quantity'      => (int) $item->quantity,
                'total_price'   => round((float) (($item->price_at_time_of_purchase ?? $item->unit_price ?? 0) * $item->quantity), 2),
            ];
        });

        return $base;
    }
}
