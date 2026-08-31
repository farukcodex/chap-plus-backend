<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Order;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $filter = $request->query('filter', 'active'); // active, completed, cancelled

        $query = Order::with(['items.product.images', 'merchantProfile'])
            ->where('user_id', $request->user()->id);

        if ($filter === 'active') {
            $query->whereIn('status', ['pending_payment', 'failed', 'paid', 'processing', 'on_the_way']);
        } elseif ($filter === 'completed') {
            $query->where('status', 'delivered');
        } elseif ($filter === 'cancelled') {
            $query->whereIn('status', ['cancelled']);
        }

        $orders = $query->latest()->paginate(10);

        return $this->apiSuccess('Orders retrieved successfully', ['orders' => $orders]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $order = Order::with(['items.product.images', 'items.variant', 'merchantProfile', 'rider'])
            ->where('user_id', $request->user()->id)
            ->find($id);

        if (!$order) {
            return $this->apiError('Order not found', 404);
        }

        $orderData = $order->toArray();
        $orderData['live_location'] = null;

        if ($order->status === 'on_the_way') {
            $orderData['live_location'] = \Illuminate\Support\Facades\Cache::get('order_' . $order->id . '_location');
        }

        return $this->apiSuccess('Order details retrieved', ['order' => $orderData]);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:255'
        ]);

        $order = Order::where('user_id', $request->user()->id)->find($id);

        if (!$order) {
            return $this->apiError('Order not found', 404);
        }

        if (!in_array($order->status, ['pending_payment', 'paid'])) {
            return $this->apiError("You cannot cancel an order that is already {$order->status}", 400);
        }

        $order->update([
            'status' => 'cancelled',
            'cancellation_reason' => $validated['reason']
        ]);

        return $this->apiSuccess('Order cancelled successfully', ['order' => $order]);
    }

    public function review(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string'
        ]);

        $order = Order::where('user_id', $request->user()->id)->find($id);

        if (!$order) {
            return $this->apiError('Order not found', 404);
        }

        if ($order->status !== 'delivered') {
            return $this->apiError('You can only review delivered orders', 400);
        }

        $order->update([
            'rating' => $validated['rating'],
            'review_comment' => $validated['comment']
        ]);

        return $this->apiSuccess('Review submitted successfully', ['order' => $order]);
    }

    public function tracking(Request $request, string $id): JsonResponse
    {
        $order = Order::with(['rider.riderProfile'])->where('user_id', $request->user()->id)->find($id);

        if (!$order) {
            return $this->apiError('Order not found', 404);
        }

        // Fake timeline data for the UI
        $timeline = [
            'order_confirmed' => in_array($order->status, ['paid', 'processing', 'on_the_way', 'delivered']),
            'preparing' => in_array($order->status, ['processing', 'on_the_way', 'delivered']),
            'on_the_way' => in_array($order->status, ['on_the_way', 'delivered']),
            'delivered' => $order->status === 'delivered',
        ];

        return $this->apiSuccess('Order tracking info retrieved', [
            'status' => $order->status,
            'timeline' => $timeline,
            'rider' => $order->rider
        ]);
    }
}
