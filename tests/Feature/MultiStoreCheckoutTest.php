<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CountryDeliveryFee;
use App\Models\MerchantProfile;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\DistanceService;
use App\Services\MpesaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MultiStoreCheckoutTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('pdo_sqlite') && config('database.default') === 'sqlite') {
            $this->markTestSkipped('SQLite PDO driver is not available.');
        }
    }

    public function test_multi_store_cart_and_checkout_flow(): void
    {
        // 1. Setup Country Delivery Fees
        CountryDeliveryFee::create(['country' => 'KE', 'fee_amount' => 5.00, 'currency' => 'KES']);
        CountryDeliveryFee::create(['country' => 'TZ', 'fee_amount' => 7.00, 'currency' => 'TZS']);

        // 2. Setup Category
        $category = ProductCategory::create([
            'name' => 'General',
            'slug' => 'general',
            'type' => 'ecommerce',
            'is_active' => true,
        ]);

        // 3. Setup Merchant 1 (Kenya)
        $merchantUser1 = User::factory()->create();
        $merchant1 = MerchantProfile::create([
            'user_id' => $merchantUser1->id,
            'business_name' => 'Store Kenya',
            'country' => 'KE',
            'currency' => 'KES',
            'is_approved' => true,
        ]);

        $product1 = Product::create([
            'merchant_profile_id' => $merchant1->id,
            'category_id' => $category->id,
            'name' => 'Product 1',
            'slug' => 'product-1',
            'base_price' => 100.00,
            'is_active' => true,
        ]);

        // 4. Setup Merchant 2 (Tanzania)
        $merchantUser2 = User::factory()->create();
        $merchant2 = MerchantProfile::create([
            'user_id' => $merchantUser2->id,
            'business_name' => 'Store Tanzania',
            'country' => 'TZ',
            'currency' => 'TZS',
            'is_approved' => true,
        ]);

        $product2 = Product::create([
            'merchant_profile_id' => $merchant2->id,
            'category_id' => $category->id,
            'name' => 'Product 2',
            'slug' => 'product-2',
            'base_price' => 50.00,
            'is_active' => true,
        ]);

        // 5. Setup Customer & Address
        $customer = User::factory()->create();
        $address = UserAddress::create([
            'user_id' => $customer->id,
            'title' => 'Home',
            'address_text' => '123 Main St, Nairobi',
            'phone_number' => '254700000000',
            'latitude' => -1.286389,
            'longitude' => 36.817223,
        ]);

        // 6. Setup Cart with items from both stores
        $cart = Cart::create([
            'user_id' => $customer->id,
            'type' => 'ecommerce',
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product1->id,
            'quantity' => 2, // 2 * 100 = 200
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product2->id,
            'quantity' => 1, // 1 * 50 = 50
        ]);

        // 7. Verify Cart API groups by store with per-store delivery fees
        $cartResponse = $this->actingAs($customer, 'sanctum')->getJson('/api/ecommerce/cart');
        $cartResponse->assertStatus(200);
        $cartData = $cartResponse->json('data');

        $this->assertCount(2, $cartData['stores']);
        $this->assertEquals(2, $cartData['summary']['stores_count']);
        $this->assertEquals(250.00, $cartData['summary']['sub_total']);
        // Delivery fee: Store 1 (5.00) + Store 2 (7.00) = 12.00
        $this->assertEquals(12.00, $cartData['summary']['delivery_charge']);
        $this->assertEquals(262.00, $cartData['summary']['total_cost']);

        // 8. Mock MpesaService & DistanceService for Checkout
        $mockMpesa = Mockery::mock(MpesaService::class);
        $mockMpesa->shouldReceive('initiateStkPush')
            ->once()
            ->withArgs(function ($phone, $amount, $ref, $desc) {
                return $phone === '254712345678' && $amount == 262.00;
            })
            ->andReturn([
                'ResponseCode' => '0',
                'ResponseDescription' => 'Success',
                'MerchantRequestID' => 'MR-12345',
                'CheckoutRequestID' => 'ws_CO_TEST_BATCH_123',
                'CustomerMessage' => 'Success',
            ]);
        $this->app->instance(MpesaService::class, $mockMpesa);

        $mockDistance = Mockery::mock(DistanceService::class);
        $mockDistance->shouldReceive('calculate')
            ->andReturn(['distance_km' => 5.2, 'duration_minute' => 15]);
        $this->app->instance(DistanceService::class, $mockDistance);

        // 9. Process Checkout
        $checkoutResponse = $this->actingAs($customer, 'sanctum')->postJson('/api/ecommerce/checkout', [
            'user_address_id' => $address->id,
            'phone_number' => '254712345678',
        ]);

        $checkoutResponse->assertStatus(200);
        $checkoutData = $checkoutResponse->json('data');

        $this->assertEquals(2, $checkoutData['orders_count']);
        $this->assertNotEmpty($checkoutData['batch_id']);
        $this->assertEquals(262.00, $checkoutData['grand_total']);

        // Verify 2 orders created in database
        $orders = Order::where('order_batch_id', $checkoutData['batch_id'])->get();
        $this->assertCount(2, $orders);

        $order1 = $orders->firstWhere('merchant_profile_id', $merchant1->id);
        $order2 = $orders->firstWhere('merchant_profile_id', $merchant2->id);

        $this->assertNotNull($order1);
        $this->assertEquals(200.00, (float) $order1->total_amount);
        $this->assertEquals(5.00, (float) $order1->delivery_fee);
        $this->assertEquals('pending_payment', $order1->status);
        $this->assertEquals('ws_CO_TEST_BATCH_123', $order1->mpesa_checkout_request_id);

        $this->assertNotNull($order2);
        $this->assertEquals(50.00, (float) $order2->total_amount);
        $this->assertEquals(7.00, (float) $order2->delivery_fee);
        $this->assertEquals('pending_payment', $order2->status);
        $this->assertEquals('ws_CO_TEST_BATCH_123', $order2->mpesa_checkout_request_id);

        // Verify cart items were cleared
        $this->assertDatabaseCount('cart_items', 0);

        // 10. Simulate Webhook callback
        $webhookResponse = $this->postJson('/api/webhooks/mpesa/simulate', [
            'order_id' => $order1->id,
            'status' => 'success',
        ]);

        $webhookResponse->assertStatus(200);

        // Both orders should now be paid!
        $order1->refresh();
        $order2->refresh();

        $this->assertEquals('paid', $order1->status);
        $this->assertEquals('paid', $order2->status);
        $this->assertNotEmpty($order1->mpesa_receipt_number);
        $this->assertNotEmpty($order2->mpesa_receipt_number);
    }
}
