<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Order;
use App\Models\User;
use App\Notifications\Customer\OrderStatusUpdatedNotification;
use App\Notifications\Rider\NewDeliveryAvailableNotification;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $merchantProfile = $request->user()->merchantProfile;

        if (!$merchantProfile) {
            return $this->apiError('Merchant profile not found.', 404);
        }

        $status = $request->query('status'); 

        $query = Order::with(['items.product.images'])
            ->where('merchant_profile_id', $merchantProfile->id);

        if ($status) {
            // Support multiple statuses if comma-separated (e.g. ?status=delivered,cancelled,failed)
            $statuses = array_map('trim', explode(',', $status));
            $query->whereIn('status', $statuses);
        } else {
            // Default: Fetch all except 'pending_payment' because merchants shouldn't prepare unpaid orders
            $query->where('status', '!=', 'pending_payment');
        }

        $orders = $query->latest()->paginate(15);

        return $this->apiSuccess('Merchant orders retrieved successfully', ['orders' => $orders]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $merchantProfile = $request->user()->merchantProfile;

        $order = Order::with(['items.product.images', 'items.variant', 'rider'])
            ->where('merchant_profile_id', $merchantProfile->id)
            ->find($id);

        if (!$order) {
            return $this->apiError('Order not found or you do not have permission to view it.', 404);
        }

        return $this->apiSuccess('Order details retrieved', ['order' => $order]);
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status'              => 'required|string|in:processing,ready_for_pickup,cancelled',
            'cancellation_reason' => 'nullable|string|max:500',
        ]);

        $merchantProfile = $request->user()->merchantProfile;

        $order = Order::with(['user', 'merchantProfile'])->where('merchant_profile_id', $merchantProfile->id)->find($id);

        if (!$order) {
            return $this->apiError('Order not found', 404);
        }

        // State Machine Security Validation
        if ($validated['status'] === 'processing' && $order->status !== 'paid') {
            return $this->apiError('You can only accept (process) orders that are currently "paid".', 400);
        }

        if ($validated['status'] === 'ready_for_pickup' && $order->status !== 'processing') {
            return $this->apiError('You can only mark an order as ready if it is currently "processing".', 400);
        }

        if ($validated['status'] === 'cancelled' && in_array($order->status, ['accepted', 'picked_up', 'on_the_way', 'delivered'])) {
            return $this->apiError('You cannot cancel an order that is already accepted, picked up, on the way, or delivered.', 400);
        }

        $updateData = ['status' => $validated['status']];
        if (!empty($validated['cancellation_reason'])) {
            $updateData['cancellation_reason'] = $validated['cancellation_reason'];
        }
        $order->update($updateData);

        // Notify customer (In-App + Push, and Email if cancelled)
        $order->user?->notify(new OrderStatusUpdatedNotification(
            $order,
            $validated['status'],
            $validated['cancellation_reason'] ?? null
        ));

        // When order is ready for pickup, alert riders that a new delivery is available
        if ($validated['status'] === 'ready_for_pickup') {
            User::role('rider')->get()->each(function ($rider) use ($order) {
                $rider->notify(new NewDeliveryAvailableNotification($order));
            });
        }

        return $this->apiSuccess('Order status updated successfully', ['order' => $order]);
    }
}
