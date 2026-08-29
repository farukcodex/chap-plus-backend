<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\MpesaService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    use ApiResponseTrait;

    protected $mpesaService;

    public function __construct(MpesaService $mpesaService)
    {
        $this->mpesaService = $mpesaService;
    }

    public function processCheckout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'delivery_address' => 'required|string',
            'phone_number' => 'required|string', // Safaricom number for M-Pesa
        ]);

        $user = $request->user();
        $cart = Cart::with(['items.product', 'items.variant'])->where('user_id', $user->id)->first();

        if (!$cart || $cart->items->isEmpty()) {
            return $this->apiError('Your cart is empty', 400);
        }

        try {
            DB::beginTransaction();

            $total = 0;
            // Assuming for this prototype that all cart items belong to the same merchant.
            // We just grab the first item's merchant profile ID.
            $merchantId = $cart->items->first()->product->merchant_profile_id;
            
            // Delivery fee logic could be complex, for now flat $5.00
            $deliveryFee = 5.00;

            foreach ($cart->items as $item) {
                $price = $item->product->base_price;
                if ($item->variant && $item->variant->price_adjustment) {
                    $price += $item->variant->price_adjustment;
                }
                $total += ($price * $item->quantity);
            }

            $order = Order::create([
                'user_id' => $user->id,
                'merchant_profile_id' => $merchantId,
                'total_amount' => $total,
                'delivery_fee' => $deliveryFee,
                'delivery_address' => $validated['delivery_address'],
                'customer_phone_number' => $validated['phone_number'],
                'payment_method' => 'mpesa',
                'status' => 'pending_payment',
                'delivery_otp' => (string) random_int(1000, 9999),
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

            // Clear the cart
            $cart->items()->delete();

            DB::commit();

            // Total amount to charge via Mpesa
            $grandTotal = $total + $deliveryFee;

            // Initiate M-Pesa STK Push
            $mpesaResponse = $this->mpesaService->initiateStkPush(
                $validated['phone_number'],
                $grandTotal,
                'ORD-' . $order->id,
                'ChapPlus Order'
            );

            // Save the CheckoutRequestID to verify later in the webhook
            $order->update([
                'mpesa_checkout_request_id' => $mpesaResponse['CheckoutRequestID']
            ]);

            return $this->apiSuccess('Order placed! Please enter your M-Pesa PIN on your phone to complete payment.', [
                'order_id' => $order->id,
                'mpesa_response' => $mpesaResponse
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Checkout Error: " . $e->getMessage());
            return $this->apiError('Checkout failed: ' . $e->getMessage(), 500);
        }
    }

    public function retryPayment(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => 'required|string',
        ]);

        $order = Order::where('user_id', $request->user()->id)->find($id);

        if (!$order) {
            return $this->apiError('Order not found', 404);
        }

        if ($order->status === 'paid') {
            return $this->apiError('This order is already paid.', 400);
        }

        try {
            // Update the phone number in case they want to try a different one
            $order->update([
                'customer_phone_number' => $validated['phone_number'],
                'status' => 'pending_payment' // reset status
            ]);

            $grandTotal = $order->total_amount + $order->delivery_fee;

            // Initiate M-Pesa STK Push
            $mpesaResponse = $this->mpesaService->initiateStkPush(
                $validated['phone_number'],
                $grandTotal,
                'ORD-' . $order->id,
                'ChapPlus Retry'
            );

            // Update the request ID so the webhook can find it
            $order->update([
                'mpesa_checkout_request_id' => $mpesaResponse['CheckoutRequestID']
            ]);

            return $this->apiSuccess('Payment retry initiated! Please check your phone.', [
                'order_id' => $order->id,
                'mpesa_response' => $mpesaResponse
            ]);

        } catch (\Exception $e) {
            Log::error("Retry Payment Error: " . $e->getMessage());
            return $this->apiError('Failed to retry payment: ' . $e->getMessage(), 500);
        }
    }

    public function mpesaWebhook(Request $request): JsonResponse
    {
        Log::info('M-Pesa Webhook Callback Received', $request->all());

        $callbackData = $request->input('Body.stkCallback');

        if (!$callbackData) {
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Invalid Callback Payload']);
        }

        $resultCode = $callbackData['ResultCode'];
        $checkoutRequestId = $callbackData['CheckoutRequestID'];

        $order = Order::where('mpesa_checkout_request_id', $checkoutRequestId)->first();

        if (!$order) {
            Log::error('M-Pesa Webhook: Order not found for CheckoutRequestID ' . $checkoutRequestId);
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        // Idempotency check: If the order is already processed, ignore duplicate webhooks
        if ($order->status === 'paid' || $order->status === 'failed') {
            Log::info('M-Pesa Webhook: Ignored duplicate callback for Order #' . $order->id);
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        if ($resultCode == 0) {
            // Payment Successful
            $callbackMetadata = $callbackData['CallbackMetadata']['Item'];
            $receiptNumber = null;

            foreach ($callbackMetadata as $item) {
                if ($item['Name'] === 'MpesaReceiptNumber') {
                    $receiptNumber = $item['Value'];
                    break;
                }
            }

            $order->update([
                'status' => 'paid',
                'mpesa_receipt_number' => $receiptNumber
            ]);

            Log::info("Order #{$order->id} paid successfully via M-Pesa. Receipt: {$receiptNumber}");
        } else {
            // Payment Failed or Cancelled by user
            $order->update([
                'status' => 'failed'
            ]);
            Log::info("Order #{$order->id} M-Pesa payment failed. Reason: {$callbackData['ResultDesc']}");
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }
}
