<?php

namespace App\Http\Controllers\Merchant;

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
        $merchantProfile = $request->user()->merchantProfile;

        if (!$merchantProfile) {
            return $this->apiError('Merchant profile not found.', 404);
        }

        $tab = $request->query('status', 'all'); 
        // Expected from UI: all, on_the_way, delivered, cancelled

        $query = Order::with(['items.product.images'])
            ->where('merchant_profile_id', $merchantProfile->id);

        // We exclude 'pending_payment' because merchants shouldn't prepare unpaid orders
        if ($tab === 'all') {
            $query->where('status', '!=', 'pending_payment');
        } elseif ($tab === 'on_the_way') {
            $query->where('status', 'on_the_way');
        } elseif ($tab === 'delivered') {
            $query->where('status', 'delivered');
        } elseif ($tab === 'cancelled') {
            $query->whereIn('status', ['cancelled', 'failed']);
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
            'status' => 'required|string|in:processing,ready_for_pickup,cancelled'
        ]);

        $merchantProfile = $request->user()->merchantProfile;

        $order = Order::where('merchant_profile_id', $merchantProfile->id)->find($id);

        if (!$order) {
            return $this->apiError('Order not found', 404);
        }

        // Merchants can only start processing an order if it's currently paid
        if ($validated['status'] === 'processing' && $order->status !== 'paid') {
            return $this->apiError('You can only process orders that have been successfully paid.', 400);
        }

        $order->update(['status' => $validated['status']]);

        return $this->apiSuccess('Order status updated successfully', ['order' => $order]);
    }
}
