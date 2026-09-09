<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Product;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Http\Resources\Customer\RestaurantFoodResource;
use App\Http\Resources\Customer\EcommerceProductResource;

class FavoriteController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $query = Favorite::with(['product.images', 'product.variants', 'product.category.parent', 'product.merchantProfile'])
            ->where('user_id', $request->user()->id);

        $isRestaurant = $request->is('*restaurant*') || $request->query('type') === 'restaurant';

        if ($isRestaurant) {
            $query->whereHas('product.merchantProfile.user.roles', function ($r) {
                $r->where('name', 'RESTAURANT_MERCHANT');
            });
        } elseif ($request->is('*ecommerce*') || $request->query('type') === 'ecommerce') {
            $query->whereHas('product.merchantProfile.user.roles', function ($r) {
                $r->where('name', 'ECOMMERCE_MERCHANT');
            });
        }

        $favorites = $query->get()
            ->map(function ($favorite) use ($request, $isRestaurant) {
                $product = $favorite->product;
                if (!$product) {
                    return null;
                }
                $product->is_favorite = true;
                if ($isRestaurant) {
                    return (new RestaurantFoodResource($product))->toArray($request);
                }
                return (new EcommerceProductResource($product))->toArray($request);
            })
            ->filter()
            ->values();

        return $this->apiSuccess('Favorites retrieved successfully', ['products' => $favorites]);
    }

    public function toggle(Request $request, int $productId): JsonResponse
    {
        $product = Product::find($productId);

        if (!$product) {
            return $this->apiError('Product not found', 404, ['code' => 'PRODUCT_NOT_FOUND']);
        }

        $userId = $request->user()->id;

        $favorite = Favorite::where('user_id', $userId)
            ->where('product_id', $productId)
            ->first();

        if ($favorite) {
            $favorite->delete();
            return $this->apiSuccess('Removed from favorites', ['is_favorite' => false]);
        }

        Favorite::create([
            'user_id' => $userId,
            'product_id' => $productId,
        ]);

        return $this->apiSuccess('Added to favorites', ['is_favorite' => true]);
    }
}
