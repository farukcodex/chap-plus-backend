<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class CartController extends Controller
{
    use ApiResponseTrait;

    public function getCart(Request $request): JsonResponse
    {
        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);
        
        $cart->load(['items.product.merchantProfile', 'items.variant']);

        // Calculate totals dynamically
        $subTotal = 0;
        $deliveryCharge = 0;
        
        // Fallback to the user's local currency if the cart is empty
        $currency = $request->user()->userProfile->currency ?? 'USD';

        if ($cart->items->isNotEmpty()) {
            $merchantProfile = $cart->items->first()->product->merchantProfile;
            // Override with merchant's currency if available
            $currency = $merchantProfile->currency ?? $currency;
            
            // Get country delivery fee
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

        return $this->apiSuccess('Cart retrieved', [
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
            'quantity' => 'required|integer|min:1'
        ]);

        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);

        $product = Product::findOrFail($validated['product_id']);
        if (!$product->is_active) {
            return $this->apiError('Product is not available', 400);
        }

        // Check if item already in cart
        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $validated['product_id'])
            ->where('product_variant_id', $validated['product_variant_id'] ?? null)
            ->first();

        if ($cartItem) {
            $cartItem->quantity += $validated['quantity'];
            $cartItem->save();
        } else {
            $cartItem = CartItem::create([
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

        $cart = Cart::where('user_id', $request->user()->id)->first();
        if (!$cart) {
            return $this->apiError('Cart not found', 404);
        }

        $cartItem = CartItem::where('cart_id', $cart->id)->find($id);
        if (!$cartItem) {
            return $this->apiError('Item not found in cart', 404);
        }

        $cartItem->update(['quantity' => $validated['quantity']]);

        return $this->getCart($request);
    }

    public function removeFromCart(Request $request, string $id): JsonResponse
    {
        $cart = Cart::where('user_id', $request->user()->id)->first();
        if (!$cart) {
            return $this->apiError('Cart not found', 404);
        }

        $cartItem = CartItem::where('cart_id', $cart->id)->find($id);
        if (!$cartItem) {
            return $this->apiError('Item not found in cart', 404);
        }

        $cartItem->delete();

        return $this->getCart($request);
    }
}
