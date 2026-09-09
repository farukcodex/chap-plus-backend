<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class RestaurantCartController extends Controller
{
    use ApiResponseTrait;

    public function getCart(Request $request): JsonResponse
    {
        $cart = Cart::firstOrCreate([
            'user_id' => $request->user()->id,
            'type' => 'restaurant'
        ]);

        $cart->load(['items.product.merchantProfile', 'items.variant']);

        $subTotal = 0;
        $deliveryCharge = 0;
        $currency = $request->user()?->userProfile?->currency ?? 'KES';

        if ($cart->items->isNotEmpty()) {
            $merchantProfile = $cart->items->first()->product->merchantProfile;
            $currency = $merchantProfile->currency ?? $currency;

            // Delivery charge: either country delivery fee or default
            $countryFee = \App\Models\CountryDeliveryFee::where('country', $merchantProfile->country)->first();
            $deliveryCharge = $countryFee ? (float) $countryFee->fee_amount : 5.00;
        }

        foreach ($cart->items as $item) {
            $price = $item->product->base_price;
            if ($item->variant && $item->variant->price_adjustment) {
                $price += $item->variant->price_adjustment;
            }
            $subTotal += ($price * $item->quantity);
        }

        $totalCost = $subTotal + $deliveryCharge;

        return $this->apiSuccess('Restaurant food cart retrieved', [
            'cart' => $cart,
            'summary' => [
                'sub_total' => round($subTotal, 2),
                'delivery_charge' => round($deliveryCharge, 2),
                'total_cost' => round($totalCost, 2),
                'currency' => $currency
            ]
        ]);
    }

    public function addToCart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
            'clear_existing' => 'nullable|boolean',
        ]);

        $product = Product::with('merchantProfile.user.roles')->findOrFail($validated['product_id']);
        if (!$product->is_active) {
            return $this->apiError('Food item is not available', 400);
        }

        // Validate that this item is a restaurant meal
        if (!$product->merchantProfile?->user?->hasRole('RESTAURANT_MERCHANT')) {
            return $this->apiError('This item is not a restaurant food item. Please use the retail cart.', 400);
        }

        $cart = Cart::firstOrCreate([
            'user_id' => $request->user()->id,
            'type' => 'restaurant'
        ]);

        // Food delivery single-restaurant rule
        $existingItem = $cart->items()->with('product')->first();
        if ($existingItem && $existingItem->product->merchant_profile_id !== $product->merchant_profile_id) {
            if ($request->boolean('clear_existing')) {
                $cart->items()->delete();
            } else {
                return $this->apiError(
                    'Your food cart contains items from a different restaurant. Clear your cart to order from this restaurant.',
                    422,
                    ['conflict_restaurant_id' => $existingItem->product->merchant_profile_id]
                );
            }
        }

        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $validated['product_id'])
            ->where('product_variant_id', $validated['product_variant_id'] ?? null)
            ->first();

        if ($cartItem) {
            $cartItem->quantity += $validated['quantity'];
            $cartItem->save();
        } else {
            CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $validated['product_id'],
                'product_variant_id' => $validated['product_variant_id'] ?? null,
                'quantity' => $validated['quantity']
            ]);
        }

        return $this->getCart($request);
    }

    public function updateCartItem(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1'
        ]);

        $cart = Cart::where('user_id', $request->user()->id)->where('type', 'restaurant')->first();
        if (!$cart) {
            return $this->apiError('Food cart not found', 404);
        }

        $cartItem = CartItem::where('cart_id', $cart->id)->find($id);
        if (!$cartItem) {
            return $this->apiError('Item not found in food cart', 404);
        }

        $cartItem->update(['quantity' => $validated['quantity']]);

        return $this->getCart($request);
    }

    public function removeFromCart(Request $request, string $id): JsonResponse
    {
        $cart = Cart::where('user_id', $request->user()->id)->where('type', 'restaurant')->first();
        if (!$cart) {
            return $this->apiError('Food cart not found', 404);
        }

        $cartItem = CartItem::where('cart_id', $cart->id)->find($id);
        if (!$cartItem) {
            return $this->apiError('Item not found in food cart', 404);
        }

        $cartItem->delete();

        return $this->getCart($request);
    }

    public function clearCart(Request $request): JsonResponse
    {
        $cart = Cart::where('user_id', $request->user()->id)->where('type', 'restaurant')->first();
        if ($cart) {
            $cart->items()->delete();
        }

        return $this->getCart($request);
    }
}
