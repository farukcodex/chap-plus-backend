<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\UserAddress;
use App\Models\CountryDeliveryFee;
use App\Services\DistanceService;
use App\Services\MpesaService;
use App\Http\Resources\Customer\RestaurantOrderResource;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RestaurantCheckoutController extends Controller
{
    use ApiResponseTrait;

    protected $mpesaService;
    protected $distanceService;

    public function __construct(MpesaService $mpesaService, DistanceService $distanceService)
    {
        $this->mpesaService = $mpesaService;
        $this->distanceService = $distanceService;
    }

    /**
     * Process Restaurant Food Order Checkout.
     */
    public function processCheckout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_address_id' => 'required|exists:user_addresses,id',
            'phone_number' => 'required|string',
        ]);

        $user = $request->user();
        $cart = Cart::with(['items.product.merchantProfile', 'items.variant'])
            ->where('user_id', $user->id)
            ->where('type', 'restaurant')
            ->first();

        if (!$cart || $cart->items->isEmpty()) {
            return $this->apiError('Your food cart is empty', 400);
        }

        $userAddress = UserAddress::where('user_id', $user->id)->find($validated['user_address_id']);
        if (!$userAddress) {
            return $this->apiError('Invalid delivery address', 400);
        }

        try {
            DB::beginTransaction();

            $total = 0;
            $merchantProfile = $cart->items->first()->product->merchantProfile;
            $merchantId = $merchantProfile->id;

            // Determine delivery fee
            $countryFee = CountryDeliveryFee::where('country', $merchantProfile->country)->first();
            $deliveryFee = $countryFee ? (float) $countryFee->fee_amount : 5.00;

            foreach ($cart->items as $item) {
                $price = $item->product->base_price;
                if ($item->variant && $item->variant->price_adjustment) {
                    $price += $item->variant->price_adjustment;
                }
                $total += ($price * $item->quantity);
            }

            $distanceData = $this->distanceService->calculate(
                $merchantProfile->latitude ? (float) $merchantProfile->latitude : null,
                $merchantProfile->longitude ? (float) $merchantProfile->longitude : null,
                $userAddress->latitude ? (float) $userAddress->latitude : null,
                $userAddress->longitude ? (float) $userAddress->longitude : null
            );

            $order = Order::create([
                'user_id' => $user->id,
                'merchant_profile_id' => $merchantId,
                'type' => 'restaurant',
                'total_amount' => $total,
                'delivery_fee' => $deliveryFee,
                'user_address_id' => $userAddress->id,
                'payment_method' => 'mpesa',
                'status' => 'pending_payment',
                'delivery_otp' => (string) random_int(1000, 9999),
                'distance_km' => $distanceData['distance_km'] ?? null,
                'duration_minute' => $distanceData['duration_minute'] ?? null,
            ]);

            foreach ($cart->items as $item) {
                $price = $item->product->base_price;
                if ($item->variant && $item->variant->price_adjustment) {
                    $price += $item->variant->price_adjustment;
                }

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'quantity' => $item->quantity,
                    'price_at_time_of_purchase' => $price,
                ]);
            }

            // Clear the restaurant food cart
            $cart->items()->delete();

            DB::commit();

            $grandTotal = $total + $deliveryFee;

            // Initiate M-Pesa STK Push
            $mpesaResponse = $this->mpesaService->initiateStkPush(
                $validated['phone_number'],
                $grandTotal,
                'REST-' . $order->id,
                'ChapPlus Food Order'
            );

            $order->update([
                'mpesa_checkout_request_id' => $mpesaResponse['CheckoutRequestID'] ?? null
            ]);

            $order->load(['merchantProfile', 'address', 'items.product.images', 'items.variant']);

            return $this->apiSuccess('Food order placed! Please enter your M-Pesa PIN on your phone.', [
                'order_id' => $order->id,
                'order' => new RestaurantOrderResource($order),
                'mpesa_response' => $mpesaResponse
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Restaurant Checkout Error: " . $e->getMessage());
            return $this->apiError('Food checkout failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Retry payment for a restaurant food order.
     */
    public function retryPayment(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => 'required|string',
        ]);

        $order = Order::where('user_id', $request->user()->id)
            ->where('type', 'restaurant')
            ->find($id);

        if (!$order) {
            return $this->apiError('Food order not found', 404);
        }

        if ($order->status === 'paid') {
            return $this->apiError('This food order is already paid.', 400);
        }

        try {
            $order->update(['status' => 'pending_payment']);
            $grandTotal = $order->total_amount + $order->delivery_fee;

            $mpesaResponse = $this->mpesaService->initiateStkPush(
                $validated['phone_number'],
                $grandTotal,
                'REST-' . $order->id,
                'ChapPlus Food Retry'
            );

            $order->update([
                'mpesa_checkout_request_id' => $mpesaResponse['CheckoutRequestID'] ?? null
            ]);

            $order->load(['merchantProfile', 'address', 'items.product.images', 'items.variant']);

            return $this->apiSuccess('Payment retry initiated! Please check your phone.', [
                'order_id' => $order->id,
                'order' => new RestaurantOrderResource($order),
                'mpesa_response' => $mpesaResponse
            ]);

        } catch (\Exception $e) {
            Log::error("Restaurant Retry Payment Error: " . $e->getMessage());
            return $this->apiError('Failed to retry payment: ' . $e->getMessage(), 500);
        }
    }
}
