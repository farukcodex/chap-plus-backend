<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Product;
use App\Models\MerchantProfile;

class DeliverySeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure we have a rider
        // Assuming rider is user ID 3 from DummyUserSeeder, but let's query a rider user directly
        $rider = User::whereHas('roles', function ($q) {
            $q->where('name', 'rider');
        })->first();

        if (!$rider) {
            return;
        }

        // Get some users to be customers
        $customers = User::whereHas('roles', function ($q) {
            $q->where('name', 'user');
        })->get();
        if ($customers->isEmpty()) {
            $customers = User::all();
        }

        // Get some products and merchants
        $products = Product::with('merchantProfile')->get();
        if ($products->isEmpty()) {
            return;
        }

        // Helper to create an order
        $createOrder = function($status, $riderId = null) use ($customers, $products) {
            $customer = $customers->random();
            $product1 = $products->random();
            $product2 = $products->random();
            $merchant = $product1->merchantProfile;

            if (!$merchant) {
                $merchant = MerchantProfile::first();
            }

            $deliveryFee = 5.00;
            $totalAmount = ($product1->base_price * 2) + ($product2->base_price * 1) + $deliveryFee;

            $userAddress = \App\Models\UserAddress::firstOrCreate(
                ['user_id' => $customer->id],
                [
                    'title' => 'Home',
                    'address_text' => fake()->streetAddress() . ', ' . fake()->city(),
                    'phone_number' => '+2547' . fake()->numerify('########'),
                    'latitude' => fake()->latitude(),
                    'longitude' => fake()->longitude(),
                ]
            );

            $order = Order::factory()->create([
                'user_id' => $customer->id,
                'merchant_profile_id' => $merchant->id,
                'status' => $status,
                'rider_id' => $riderId,
                'delivery_otp' => '1234',
                'delivery_fee' => $deliveryFee,
                'total_amount' => $totalAmount,
                'user_address_id' => $userAddress->id,
            ]);

            OrderItem::factory()->create([
                'order_id' => $order->id,
                'product_id' => $product1->id,
                'price_at_time_of_purchase' => $product1->base_price,
                'quantity' => 2,
            ]);

            OrderItem::factory()->create([
                'order_id' => $order->id,
                'product_id' => $product2->id,
                'price_at_time_of_purchase' => $product2->base_price,
                'quantity' => 1,
            ]);
        };

        // Create Available Deliveries (status: ready_for_pickup, rider_id: null)
        for ($i = 0; $i < 10; $i++) {
            $createOrder('ready_for_pickup');
        }

        // Create Active Deliveries for our Rider (status: on_the_way, rider_id: rider->id)
        for ($i = 0; $i < 10; $i++) {
            $createOrder('on_the_way', $rider->id);
        }

        // Create Completed Deliveries for our Rider (status: delivered, rider_id: rider->id)
        for ($i = 0; $i < 10; $i++) {
            $createOrder('delivered', $rider->id);
        }
    }
}