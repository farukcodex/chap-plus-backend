<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Http\Resources\Customer\EcommerceOrderResource;
use App\Models\Cart;
use App\Models\CountryDeliveryFee;
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
use Illuminate\Support\Str;

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
            'phone_number'    => 'required|string', // Safaricom number for M-Pesa
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

            $orderBatchId = 'BATCH-' . strtoupper(Str::random(10));
            $createdOrders = [];
            $grandTotal = 0;

            // Group cart items by merchant_profile_id
            $grouped = $cart->items->groupBy(function ($item) {
                return $item->product?->merchant_profile_id ?? 0;
            });

            // Cache country delivery fees
            $countryFees = CountryDeliveryFee::all()->keyBy(fn ($f) => strtoupper($f->country));

            foreach ($grouped as $merchantId => $items) {
                $merchantProfile = $items->first()->product?->merchantProfile;
                $merchantCountry = strtoupper((string) ($merchantProfile?->country ?? ''));
                $countryFee = $countryFees->get($merchantCountry);
                $deliveryFee = $countryFee ? (float) $countryFee->fee_amount : 5.00;

                $storeSubtotal = 0;
                foreach ($items as $item) {
                    $price = (float) ($item->product?->base_price ?? 0);
                    if ($item->variant && $item->variant->price_adjustment) {
                        $price += (float) $item->variant->price_adjustment;
                    }
                    $storeSubtotal += ($price * (float) $item->quantity);
                }

                $distanceData = $this->distanceService->calculate(
                    $merchantProfile?->latitude ? (float) $merchantProfile->latitude : null,
                    $merchantProfile?->longitude ? (float) $merchantProfile->longitude : null,
                    $userAddress->latitude ? (float) $userAddress->latitude : null,
                    $userAddress->longitude ? (float) $userAddress->longitude : null
                );

                $order = Order::create([
                    'user_id'             => $user->id,
                    'merchant_profile_id' => $merchantId ?: null,
                    'order_batch_id'      => $orderBatchId,
                    'type'                => 'ecommerce',
                    'total_amount'        => $storeSubtotal,
                    'delivery_fee'        => $deliveryFee,
                    'user_address_id'     => $userAddress->id,
                    'payment_method'      => 'mpesa',
                    'status'              => 'pending_payment',
                    'delivery_otp'        => (string) random_int(1000, 9999),
                    'distance_km'         => $distanceData['distance_km'] ?? null,
                    'duration_minute'     => $distanceData['duration_minute'] ?? null,
                ]);

                foreach ($items as $item) {
                    $price = (float) ($item->product?->base_price ?? 0);
                    if ($item->variant && $item->variant->price_adjustment) {
                        $price += (float) $item->variant->price_adjustment;
                    }

                    OrderItem::create([
                        'order_id'                  => $order->id,
                        'product_id'                => $item->product_id,
                        'product_variant_id'        => $item->product_variant_id,
                        'quantity'                  => $item->quantity,
                        'price_at_time_of_purchase' => $price,
                    ]);
                }

                $grandTotal += ($storeSubtotal + $deliveryFee);
                $createdOrders[] = $order;
            }

            // Clear the cart
            $cart->items()->delete();

            DB::commit();

            // Total amount to charge via M-Pesa
            $grandTotal = round($grandTotal, 2);

            // Account Reference: Safaricom limits to max 12 characters alphanumeric
            $accountRef = count($createdOrders) === 1
                ? 'ORD-' . $createdOrders[0]->id
                : substr(str_replace('-', '', $orderBatchId), 0, 12);

            // Initiate single M-Pesa STK Push for grand total
            $mpesaResponse = $this->mpesaService->initiateStkPush(
                $validated['phone_number'],
                $grandTotal,
                $accountRef,
                'ChapPlus Order'
            );

            // Save the CheckoutRequestID to all orders in this batch
            $checkoutRequestId = $mpesaResponse['CheckoutRequestID'] ?? null;
            if ($checkoutRequestId) {
                $orderIds = collect($createdOrders)->pluck('id');
                Order::whereIn('id', $orderIds)->update([
                    'mpesa_checkout_request_id' => $checkoutRequestId
                ]);
            }

            // Load relations for response
            foreach ($createdOrders as $order) {
                $order->load(['merchantProfile', 'address', 'items.product.images', 'items.variant']);
            }

            return $this->apiSuccess('Order placed! Please enter your M-Pesa PIN on your phone to complete payment.', [
                'batch_id'       => $orderBatchId,
                'orders_count'   => count($createdOrders),
                'orders'         => EcommerceOrderResource::collection(collect($createdOrders)),
                'grand_total'    => $grandTotal,
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

        if (!empty($order->order_batch_id)) {
            $orders = Order::where('user_id', $request->user()->id)
                ->where('order_batch_id', $order->order_batch_id)
                ->where('status', '!=', 'paid')
                ->get();
        } else {
            $orders = collect([$order]);
        }

        if ($orders->isEmpty() || $order->status === 'paid') {
            return $this->apiError('This order is already paid.', 400);
        }

        try {
            $grandTotal = 0;
            foreach ($orders as $o) {
                $o->update(['status' => 'pending_payment']);
                $grandTotal += ((float) $o->total_amount + (float) $o->delivery_fee);
            }
            $grandTotal = round($grandTotal, 2);

            $accountRef = $orders->count() === 1
                ? 'ORD-' . $orders[0]->id
                : ($orders[0]->order_batch_id ? substr(str_replace('-', '', $orders[0]->order_batch_id), 0, 12) : 'ORD-' . $orders[0]->id);

            // Initiate M-Pesa STK Push
            $mpesaResponse = $this->mpesaService->initiateStkPush(
                $validated['phone_number'],
                $grandTotal,
                $accountRef,
                'ChapPlus Retry'
            );

            // Update the request ID so the webhook can find all batch orders
            $checkoutRequestId = $mpesaResponse['CheckoutRequestID'] ?? null;
            if ($checkoutRequestId) {
                Order::whereIn('id', $orders->pluck('id'))->update([
                    'mpesa_checkout_request_id' => $checkoutRequestId
                ]);
            }

            return $this->apiSuccess('Payment retry initiated! Please check your phone.', [
                'order_id'       => $order->id,
                'batch_id'       => $order->order_batch_id,
                'orders_count'   => $orders->count(),
                'grand_total'    => $grandTotal,
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

        $orders = Order::with(['merchantProfile.user', 'user'])->where('mpesa_checkout_request_id', $checkoutRequestId)->get();

        if ($orders->isEmpty()) {
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
                        $merchantCommissionPercent = \App\Models\PlatformSetting::getCommissionRate('bus');
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

        // Idempotency check: If all orders are already paid, ignore duplicate webhooks
        if ($orders->every(fn ($o) => $o->status === 'paid')) {
            Log::info('M-Pesa Webhook: Ignored duplicate callback for Orders with CheckoutRequestID ' . $checkoutRequestId);
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        if ($resultCode == 0) {
            // Payment Successful
            $callbackMetadata = $callbackData['CallbackMetadata']['Item'] ?? [];
            $receiptNumber = null;

            foreach ($callbackMetadata as $item) {
                if (($item['Name'] ?? '') === 'MpesaReceiptNumber') {
                    $receiptNumber = $item['Value'];
                    break;
                }
            }

            foreach ($orders as $order) {
                $order->update([
                    'status' => 'paid',
                    'mpesa_receipt_number' => $receiptNumber
                ]);

                // Send multi-channel notification to customer (Push, In-App, Email invoice)
                try {
                    $order->user?->notify(new OrderPlacedNotification($order));
                } catch (\Throwable $e) {
                    Log::error("Failed customer notification for Order #{$order->id}: " . $e->getMessage());
                }

                // Notify merchant user about new incoming order
                try {
                    $merchantUser = $order->merchantProfile?->user;
                    if ($merchantUser) {
                        $merchantUser->notify(new OrderStatusUpdatedNotification($order, 'paid', 'New paid order received!'));
                    }
                } catch (\Throwable $e) {
                    Log::error("Failed merchant notification for Order #{$order->id}: " . $e->getMessage());
                }

                // Notify all platform administrators
                try {
                    \App\Services\AdminNotificationService::notifyAdmins(
                        new \App\Notifications\Admin\NewOrderPlacedAdminNotification($order)
                    );
                } catch (\Throwable $e) {
                    Log::error("Failed admin notification for Order #{$order->id}: " . $e->getMessage());
                }

                Log::info("Order #{$order->id} paid successfully via M-Pesa. Receipt: {$receiptNumber}");
            }
        } else {
            // Payment Failed or Cancelled by user
            $resultDesc = $callbackData['ResultDesc'] ?? 'M-Pesa payment was cancelled or failed';
            foreach ($orders as $order) {
                $order->update([
                    'status' => 'failed'
                ]);

                // Send notification of failed/cancelled order
                try {
                    $order->user?->notify(new OrderStatusUpdatedNotification(
                        $order,
                        'cancelled',
                        'M-Pesa payment was cancelled or failed: ' . $resultDesc
                    ));
                } catch (\Throwable $e) {
                    Log::error("Failed customer cancel notification for Order #{$order->id}: " . $e->getMessage());
                }

                Log::info("Order #{$order->id} M-Pesa payment failed. Reason: {$resultDesc}");
            }
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
            if (!empty($order->order_batch_id)) {
                $batchOrders = Order::where('order_batch_id', $order->order_batch_id)->get();
            } else {
                $batchOrders = collect([$order]);
            }
            if (empty($order->mpesa_checkout_request_id)) {
                $simReqId = 'ws_SIM_' . time();
                Order::whereIn('id', $batchOrders->pluck('id'))->update(['mpesa_checkout_request_id' => $simReqId]);
                $checkoutRequestId = $simReqId;
            } else {
                $checkoutRequestId = $order->mpesa_checkout_request_id;
            }
            $amount = (float) $batchOrders->sum(fn ($o) => (float) $o->total_amount + (float) $o->delivery_fee);
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

    /**
     * Webhook callback alias
     */
    public function handleCallback(Request $request): JsonResponse
    {
        return $this->mpesaWebhook($request);
    }
}
