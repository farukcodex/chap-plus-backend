<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Http\Resources\Customer\EcommerceOrderResource;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Notifications\Customer\BusBookingConfirmedNotification;
use App\Notifications\Customer\BusBookingPaymentFailedNotification;
use App\Notifications\Customer\HotelBookingConfirmedNotification;
use App\Notifications\Customer\OrderPlacedNotification;
use App\Notifications\Customer\OrderStatusUpdatedNotification;
use App\Services\DistanceService;
use App\Services\MpesaService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    use ApiResponseTrait;

    protected $mpesaService;
    protected $distanceService;

    public function __construct(MpesaService $mpesaService, DistanceService $distanceService)
    {
        $this->mpesaService = $mpesaService;
        $this->distanceService = $distanceService;
    }

    public function processCheckout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_address_id' => 'required|exists:user_addresses,id',
            'phone_number' => 'required|string', // Safaricom number for M-Pesa
        ]);

        $user = $request->user();
        $cart = Cart::with(['items.product.merchantProfile', 'items.variant'])
            ->where('user_id', $user->id)
            ->where('type', 'ecommerce')
            ->first();

        if (!$cart || $cart->items->isEmpty()) {
            return $this->apiError('Your cart is empty', 400);
        }
        
        $userAddress = \App\Models\UserAddress::where('user_id', $user->id)->find($validated['user_address_id']);
        if (!$userAddress) {
            return $this->apiError('Invalid delivery address', 400);
        }

        try {
            DB::beginTransaction();

            $total = 0;
            // Assuming for this prototype that all cart items belong to the same merchant.
            // We just grab the first item's merchant profile ID and country.
            $merchantProfile = $cart->items->first()->product->merchantProfile;
            $merchantId = $merchantProfile->id;
            
            // Determine delivery fee based on merchant's country
            $countryFee = \App\Models\CountryDeliveryFee::where('country', $merchantProfile->country)->first();
            $deliveryFee = $countryFee ? $countryFee->fee_amount : 5.00; // fallback to 5.00 if country not found

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
                'type' => 'ecommerce',
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

            $order->load(['merchantProfile', 'address', 'items.product.images', 'items.variant']);

            return $this->apiSuccess('Order placed! Please enter your M-Pesa PIN on your phone to complete payment.', [
                'order_id' => $order->id,
                'order' => new EcommerceOrderResource($order),
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
            $order->update([
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

        // SAFARICOM SANDBOX QUIRK:
        // The sandbox often fires the webhook instantly, sometimes BEFORE our main 
        // checkout process has even finished receiving the CheckoutRequestID from the API 
        // and saving it to the database! We add a tiny delay to let the DB catch up.
        if (!$request->input('is_simulation')) {
            sleep(2);
        }

        $order = Order::where('mpesa_checkout_request_id', $checkoutRequestId)->first();

        if (!$order) {
            // Check if it's a Hotel Booking
            $booking = \App\Models\HotelBooking::where('mpesa_checkout_request_id', $checkoutRequestId)->first();
            
            if ($booking) {
                if ($booking->status === 'paid') {
                    return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
                }

                if ($resultCode == 0) {
                    $receiptNumber = null;
                    $callbackMetadata = $callbackData['CallbackMetadata']['Item'] ?? [];
                    foreach ($callbackMetadata as $item) {
                        if ($item['Name'] === 'MpesaReceiptNumber') {
                            $receiptNumber = $item['Value'];
                            break;
                        }
                    }
                    
                    $booking->update([
                        'status' => 'paid',
                        'mpesa_receipt_number' => $receiptNumber
                    ]);

                    // Send multi-channel notification (Push, In-App, Email voucher)
                    $booking->user?->notify(new HotelBookingConfirmedNotification($booking));

                    Log::info("Hotel Booking #{$booking->id} paid successfully via M-Pesa. Receipt: {$receiptNumber}");
                } else {
                    $booking->update(['status' => 'failed']);
                    Log::warning("Hotel Booking #{$booking->id} payment failed.");
                }
                
                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            }

            // Check if it's a Bus Booking
            $busBooking = \App\Models\BusBooking::where('mpesa_checkout_request_id', $checkoutRequestId)->first();

            if ($busBooking) {
                if ($busBooking->status === 'paid') {
                    return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
                }

                if ($resultCode == 0) {
                    $receiptNumber = null;
                    $callbackMetadata = $callbackData['CallbackMetadata']['Item'] ?? [];
                    foreach ($callbackMetadata as $item) {
                        if ($item['Name'] === 'MpesaReceiptNumber') {
                            $receiptNumber = $item['Value'];
                            break;
                        }
                    }
                    
                    \Illuminate\Support\Facades\DB::beginTransaction();
                    try {
                        $busBooking->update([
                            'status' => 'paid',
                            'mpesa_receipt_number' => $receiptNumber
                        ]);

                        // INSTANT PAYOUT LOGIC
                        $merchantCommissionPercent = \App\Models\PlatformSetting::where('key', 'merchant_commission_percent')->value('value') ?? 10.00;
                        $adminCommission = $busBooking->total_price * ($merchantCommissionPercent / 100);
                        $merchantEarnings = $busBooking->total_price - $adminCommission;

                        // Credit Admin Wallet
                        $adminUser = \App\Models\User::role('ADMIN')->first();
                        if ($adminUser) {
                            $adminWallet = \App\Models\Wallet::firstOrCreate(['user_id' => $adminUser->id]);
                            $adminWallet->increment('balance', $adminCommission);
                            \App\Models\WalletTransaction::create([
                                'wallet_id' => $adminWallet->id,
                                'type' => 'credit',
                                'amount' => $adminCommission,
                                'reference_type' => \App\Models\BusBooking::class,
                                'reference_id' => $busBooking->id,
                                'description' => "Platform commission for Bus Booking #{$busBooking->id}",
                            ]);
                        }

                        // Credit Merchant Wallet
                        $merchantUser = $busBooking->merchantProfile->user;
                        $merchantWallet = \App\Models\Wallet::firstOrCreate(['user_id' => $merchantUser->id]);
                        $merchantWallet->increment('balance', $merchantEarnings);
                        \App\Models\WalletTransaction::create([
                            'wallet_id' => $merchantWallet->id,
                            'type' => 'credit',
                            'amount' => $merchantEarnings,
                            'reference_type' => \App\Models\BusBooking::class,
                            'reference_id' => $busBooking->id,
                            'description' => "Earnings for Bus Booking #{$busBooking->id}",
                        ]);

                        \Illuminate\Support\Facades\DB::commit();

                        // Send multi-channel notification (Push, In-App, Email boarding pass)
                        $busBooking->user?->notify(new BusBookingConfirmedNotification($busBooking));

                        Log::info("Bus Booking #{$busBooking->id} paid successfully and wallets credited via M-Pesa. Receipt: {$receiptNumber}");
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\DB::rollBack();
                        Log::error("Failed to process Bus Booking payout: " . $e->getMessage());
                    }
                } else {
                    $resultDesc = $callbackData['ResultDesc'] ?? 'Payment failed or was cancelled';
                    $busBooking->update(['status' => 'failed']);
                    Log::warning("Bus Booking #{$busBooking->id} payment failed: {$resultDesc}");
                    
                    // Release the Reverb lock instantly
                    event(new \App\Events\SeatUnlockedEvent($busBooking->bus_id, $busBooking->travel_date->format('Y-m-d'), $busBooking->seat_numbers));

                    // Send multi-channel notification to customer (Email, Push, In-App)
                    try {
                        $busBooking->user?->notify(new BusBookingPaymentFailedNotification($busBooking, $resultDesc));
                    } catch (\Throwable $e) {
                        Log::error("Failed to send BusBookingPaymentFailedNotification: " . $e->getMessage());
                    }
                }
                
                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            }

            Log::error('M-Pesa Webhook: Order or Booking not found for CheckoutRequestID ' . $checkoutRequestId);
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Record not found']);
        }

        // Idempotency check: If the order is already paid, ignore duplicate webhooks
        if ($order->status === 'paid') {
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

            // Send multi-channel notification (Push, In-App, Email invoice)
            $order->user?->notify(new OrderPlacedNotification($order));

            // Notify all platform administrators
            \App\Services\AdminNotificationService::notifyAdmins(
                new \App\Notifications\Admin\NewOrderPlacedAdminNotification($order)
            );

            Log::info("Order #{$order->id} paid successfully via M-Pesa. Receipt: {$receiptNumber}");
        } else {
            // Payment Failed or Cancelled by user
            $order->update([
                'status' => 'failed'
            ]);

            // Send notification of failed/cancelled order
            $order->user?->notify(new OrderStatusUpdatedNotification(
                $order,
                'cancelled',
                'M-Pesa payment was cancelled or failed: ' . ($callbackData['ResultDesc'] ?? 'Unknown error')
            ));

            Log::info("Order #{$order->id} M-Pesa payment failed. Reason: {$callbackData['ResultDesc']}");
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    /**
     * Simulate M-Pesa STK Callback (Sandbox helper for testing Success/Failure flows)
     */
    public function simulateMpesaCallback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'booking_id' => 'nullable|integer',
            'order_id' => 'nullable|integer',
            'checkout_request_id' => 'nullable|string',
            'status' => 'nullable|string|in:success,failed',
        ]);

        $checkoutRequestId = $validated['checkout_request_id'] ?? null;
        $amount = 10.00;
        $phone = '254708374149';
        $busBooking = null;

        if (!empty($validated['booking_id'])) {
            $busBooking = \App\Models\BusBooking::find($validated['booking_id']);
            if (!$busBooking) {
                return $this->apiError('Bus Booking not found', 404);
            }
            if (empty($busBooking->mpesa_checkout_request_id)) {
                $busBooking->update(['mpesa_checkout_request_id' => 'ws_SIM_' . time()]);
            }
            $checkoutRequestId = $busBooking->mpesa_checkout_request_id;
            $amount = (float) $busBooking->total_price;
            $phone = $busBooking->passenger_phone ?? '254708374149';
        } elseif (!empty($validated['order_id'])) {
            $order = Order::find($validated['order_id']);
            if (!$order) {
                return $this->apiError('Order not found', 404);
            }
            if (empty($order->mpesa_checkout_request_id)) {
                $order->update(['mpesa_checkout_request_id' => 'ws_SIM_' . time()]);
            }
            $checkoutRequestId = $order->mpesa_checkout_request_id;
            $amount = (float) ($order->total_amount + $order->delivery_fee);
        }

        if (!$checkoutRequestId) {
            return $this->apiError('Please provide booking_id, order_id, or checkout_request_id', 400);
        }

        $isSuccess = ($validated['status'] ?? 'success') === 'success';
        $receiptNumber = 'NLJ' . strtoupper(substr(md5(uniqid()), 0, 7));

        if ($isSuccess) {
            $payload = [
                'is_simulation' => true,
                'Body' => [
                    'stkCallback' => [
                        'MerchantRequestID' => 'SIM-REQ-' . time(),
                        'CheckoutRequestID' => $checkoutRequestId,
                        'ResultCode' => 0,
                        'ResultDesc' => 'The service request is processed successfully.',
                        'CallbackMetadata' => [
                            'Item' => [
                                ['Name' => 'Amount', 'Value' => $amount],
                                ['Name' => 'MpesaReceiptNumber', 'Value' => $receiptNumber],
                                ['Name' => 'TransactionDate', 'Value' => (int) date('YmdHis')],
                                ['Name' => 'PhoneNumber', 'Value' => (int) preg_replace('/\D/', '', $phone)],
                            ]
                        ]
                    ]
                ]
            ];
        } else {
            $payload = [
                'is_simulation' => true,
                'Body' => [
                    'stkCallback' => [
                        'MerchantRequestID' => 'SIM-REQ-' . time(),
                        'CheckoutRequestID' => $checkoutRequestId,
                        'ResultCode' => 1037,
                        'ResultDesc' => 'DS timeout user cannot be reached.'
                    ]
                ]
            ];
        }

        $webhookRequest = Request::create('/api/webhooks/mpesa/callback', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
        $this->mpesaWebhook($webhookRequest);

        $updatedBooking = $busBooking ? $busBooking->fresh(['bus', 'merchantProfile']) : null;

        return $this->apiSuccess('Simulation webhook executed successfully!', [
            'status' => $isSuccess ? 'paid' : 'failed',
            'checkout_request_id' => $checkoutRequestId,
            'mpesa_receipt_number' => $isSuccess ? $receiptNumber : null,
            'amount' => $amount,
            'booking' => $updatedBooking,
        ]);
    }
}
