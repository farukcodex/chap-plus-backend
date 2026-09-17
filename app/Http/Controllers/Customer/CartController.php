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
        $cart = Cart::firstOrCreate([
            'user_id' => $request->user()->id,
            'type' => 'ecommerce'
        ]);

        $cart->load(['items.product.images', 'items.product.merchantProfile', 'items.variant']);

        // Fallback to the user's local currency if the cart is empty
        $currency = $request->user()->userProfile->currency ?? 'USD';

        $totalSubTotal = 0;
        $totalDeliveryCharge = 0;
        $stores = [];

        if ($cart->items->isNotEmpty()) {
            $countryFees = \App\Models\CountryDeliveryFee::all()->keyBy(fn ($f) => strtoupper($f->country));

            // Group items by merchant_profile_id
            $grouped = $cart->items->groupBy(function ($item) {
                return $item->product?->merchant_profile_id ?? 0;
            });

            foreach ($grouped as $merchantId => $items) {
                $merchantProfile = $items->first()->product?->merchantProfile;
                $merchantCountry = strtoupper((string) ($merchantProfile?->country ?? ''));
                $feeRecord = $countryFees->get($merchantCountry);
                $storeDeliveryFee = $feeRecord ? (float) $feeRecord->fee_amount : 5.00;

                if ($merchantProfile && !empty($merchantProfile->currency)) {
                    $currency = $merchantProfile->currency;
                }

                $storeSubTotal = 0;
                foreach ($items as $item) {
                    $price = (float) ($item->product?->base_price ?? 0);
                    if ($item->variant && $item->variant->price_adjustment) {
                        $price += (float) $item->variant->price_adjustment;
                    }
                    $storeSubTotal += ($price * (float) $item->quantity);
                }

                $storeSubTotal = round($storeSubTotal, 2);
                $storeTotal = round($storeSubTotal + $storeDeliveryFee, 2);

                $totalSubTotal += $storeSubTotal;
                $totalDeliveryCharge += $storeDeliveryFee;

                $stores[] = [
                    'merchant_id'    => $merchantId ? (int) $merchantId : null,
                    'merchant_name'  => (string) ($merchantProfile?->business_name ?? 'ChapPlus Store'),
                    'merchant_image' => $merchantProfile?->profile_image_url,
                    'currency'       => (string) ($merchantProfile?->currency ?? $currency),
                    'country'        => $merchantProfile?->country,
                    'items_count'    => $items->count(),
                    'sub_total'      => $storeSubTotal,
                    'delivery_fee'   => round($storeDeliveryFee, 2),
                    'total_cost'     => $storeTotal,
                    'items'          => $items->values(),
                ];
            }
        }

        $totalSubTotal = round($totalSubTotal, 2);
        $totalDeliveryCharge = round($totalDeliveryCharge, 2);
        $totalCost = round($totalSubTotal + $totalDeliveryCharge, 2);

        return $this->apiSuccess('Cart retrieved', [
            'cart_id' => $cart->id,
            'stores'  => $stores,
            'summary' => [
                'stores_count'    => count($stores),
                'items_count'     => $cart->items->count(),
                'sub_total'       => $totalSubTotal,
                'delivery_charge' => $totalDeliveryCharge,
                'total_cost'      => $totalCost,
                'currency'        => $currency,
            ]
        ]);
    }

    public function addToCart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'quantity' => 'nullable|integer|min:1'
        ]);

        $product = Product::with('merchantProfile.user.roles')->findOrFail($validated['product_id']);
        if (!$product->is_active) {
            return $this->apiError('Product is not available', 400);
        }

        // Validate that this item is an e-commerce product, not a restaurant meal
        if ($product->merchantProfile?->user?->hasRole('RESTAURANT_MERCHANT')) {
            return $this->apiError('This item belongs to a restaurant. Please use the restaurant food cart.', 400);
        }

        $cart = Cart::firstOrCreate([
            'user_id' => $request->user()->id,
            'type' => 'ecommerce'
        ]);

        $quantity = (int) ($validated['quantity'] ?? 1);

        // Check if item already in cart
        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $validated['product_id'])
            ->where('product_variant_id', $validated['product_variant_id'] ?? null)
            ->first();

        if ($cartItem) {
            $cartItem->quantity += $quantity;
            $cartItem->save();
        } else {
            $cartItem = CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $validated['product_id'],
                'product_variant_id' => $validated['product_variant_id'] ?? null,
                'quantity' => $quantity
            ]);
        }

        return $this->getCart($request);
    }

    public function updateCartItem(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1'
        ]);

        $cart = Cart::where('user_id', $request->user()->id)->where('type', 'ecommerce')->first();
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
        $cart = Cart::where('user_id', $request->user()->id)->where('type', 'ecommerce')->first();
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
