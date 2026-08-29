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

    public function available(): JsonResponse
    {
        // Get orders ready for pickup without a rider, hide the OTP
        $orders = Order::where('status', 'ready_for_pickup')
            ->whereNull('rider_id')
            ->with(['merchantProfile' => function ($query) {
                $query->select('id', 'business_name', 'address', 'city');
            }])
            ->latest()
            ->paginate(15);
            
        $orders->getCollection()->makeHidden('delivery_otp');

        return $this->apiSuccess('Available deliveries retrieved', ['orders' => $orders]);
    }

    public function index(Request $request): JsonResponse
    {
        $riderId = $request->user()->id;
        $tab = $request->query('tab', 'pending'); // pending or completed

        $query = Order::where('rider_id', $riderId)
            ->with(['merchantProfile' => function ($query) {
                $query->select('id', 'business_name', 'address', 'city');
            }]);

        if ($tab === 'completed') {
            $query->where('status', 'delivered');
        } else {
            // Pending / Active
            $query->whereIn('status', ['ready_for_pickup', 'on_the_way']);
        }

        $orders = $query->latest()->paginate(15);
        $orders->getCollection()->makeHidden('delivery_otp'); // Hide OTP from list view just in case

        return $this->apiSuccess('Deliveries retrieved', ['orders' => $orders]);
    }

    public function show(string $id, Request $request): JsonResponse
    {
        $order = Order::with(['merchantProfile', 'user', 'items'])->find($id);

        if (!$order) {
            return $this->apiError('Order not found', 404);
        }
        
        $order->makeHidden('delivery_otp');

        return $this->apiSuccess('Delivery details retrieved', ['order' => $order]);
    }

    public function accept(string $id, Request $request): JsonResponse
    {
        $order = Order::where('status', 'ready_for_pickup')->whereNull('rider_id')->find($id);

        if (!$order) {
            return $this->apiError('Order is no longer available', 400);
        }

        $order->update(['rider_id' => $request->user()->id]);

        return $this->apiSuccess('Delivery accepted successfully', ['order' => $order]);
    }

    public function pickup(string $id, Request $request): JsonResponse
    {
        $order = Order::where('rider_id', $request->user()->id)->where('status', 'ready_for_pickup')->find($id);

        if (!$order) {
            return $this->apiError('Invalid order or status for pickup', 400);
        }

        $order->update(['status' => 'on_the_way']);

        return $this->apiSuccess('Delivery marked as picked up', ['order' => $order]);
    }

    public function deliver(string $id, Request $request): JsonResponse
    {
        $request->validate(['otp' => 'required|string']);

        $order = Order::where('rider_id', $request->user()->id)->where('status', 'on_the_way')->find($id);

        if (!$order) {
            return $this->apiError('Invalid order or status for delivery', 400);
        }

        if ($order->delivery_otp !== $request->otp) {
            return $this->apiError('Invalid Delivery PIN', 400);
        }

        $order->update(['status' => 'delivered']);

        return $this->apiSuccess('Delivery confirmed successfully!', ['order' => $order]);
    }
}
