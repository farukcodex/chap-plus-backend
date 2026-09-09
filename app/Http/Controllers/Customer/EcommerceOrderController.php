<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Resources\Customer\EcommerceOrderResource;
use App\Models\Order;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class OrderController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $filter = $request->query('filter', 'active'); // active, completed, cancelled

        $query = Order::ecommerce()
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
        $orders->through(fn($order) => (new EcommerceOrderResource($order))->toArray($request));

        return $this->apiSuccess('Orders retrieved successfully', ['orders' => $orders]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $order = Order::ecommerce()
            ->with(['items.product.images', 'items.variant', 'merchantProfile', 'rider.riderProfile', 'address'])
            ->where('user_id', $request->user()->id)
            ->find($id);

        if (!$order) {
            return $this->apiError('Order not found', 404, ['code' => 'ORDER_NOT_FOUND']);
        }

        $orderData = (new EcommerceOrderResource($order))->toArray($request);
        $orderData['live_location'] = null;

        if (in_array($order->status, ['picked_up', 'on_the_way'])) {
            $orderData['live_location'] = Cache::get('order_' . $order->id . '_location');
        }

        return $this->apiSuccess('Order details retrieved', ['order' => $orderData]);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:255'
        ]);

        $order = Order::ecommerce()->where('user_id', $request->user()->id)->find($id);

        if (!$order) {
            return $this->apiError('Order not found', 404, ['code' => 'ORDER_NOT_FOUND']);
        }

        if (!in_array($order->status, ['pending_payment', 'paid'])) {
            return $this->apiError("You cannot cancel an order that is already {$order->status}", 400, ['code' => 'INVALID_ORDER_STATUS']);
        }

        $order->update([
            'status' => 'cancelled',
            'cancellation_reason' => $validated['reason']
        ]);

        $order->load(['merchantProfile', 'address', 'items.product.images', 'items.variant']);

        return $this->apiSuccess('Order cancelled successfully', [
            'order' => new EcommerceOrderResource($order)
        ]);
    }

    public function review(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'review_comment' => 'nullable|string|max:1000'
        ]);

        $order = Order::ecommerce()->where('user_id', $request->user()->id)->find($id);

        if (!$order) {
            return $this->apiError('Order not found', 404, ['code' => 'ORDER_NOT_FOUND']);
        }

        if ($order->status !== 'delivered') {
            return $this->apiError('You can only review delivered orders', 400, ['code' => 'INVALID_ORDER_STATUS']);
        }

        $order->update([
            'rating' => $validated['rating'],
            'review_comment' => $validated['review_comment'] ?? null
        ]);

        $order->load(['merchantProfile', 'address', 'items.product.images', 'items.variant']);

        return $this->apiSuccess('Review submitted successfully', [
            'order' => new EcommerceOrderResource($order)
        ]);
    }

    public function tracking(Request $request, string $id): JsonResponse
    {
        $order = Order::ecommerce()->with(['rider.riderProfile'])->where('user_id', $request->user()->id)->find($id);

        if (!$order) {
            return $this->apiError('Order not found', 404, ['code' => 'ORDER_NOT_FOUND']);
        }

        // Timeline data for the UI
        $timeline = [
            'order_confirmed' => in_array($order->status, ['paid', 'processing', 'ready_for_pickup', 'accepted', 'picked_up', 'on_the_way', 'delivered']),
            'preparing' => in_array($order->status, ['processing', 'ready_for_pickup', 'accepted', 'picked_up', 'on_the_way', 'delivered']),
            'ready_for_pickup' => in_array($order->status, ['ready_for_pickup', 'accepted', 'picked_up', 'on_the_way', 'delivered']),
            'accepted' => in_array($order->status, ['accepted', 'picked_up', 'on_the_way', 'delivered']),
            'picked_up' => in_array($order->status, ['picked_up', 'on_the_way', 'delivered']),
            'on_the_way' => in_array($order->status, ['on_the_way', 'delivered']),
            'delivered' => $order->status === 'delivered',
        ];

        return $this->apiSuccess('Order tracking info retrieved', [
            'status' => $order->status,
            'delivery_otp' => $order->delivery_otp,
            'timeline' => $timeline,
            'rider' => $order->rider ? [
                'id'            => (int) $order->rider->id,
                'name'          => (string) $order->rider->name,
                'phone_number'  => (string) ($order->rider->phone ?? $order->rider->riderProfile?->phone_number ?? ''),
                'profile_photo' => $order->rider->profile_photo_url,
            ] : null,
        ]);
    }
}
