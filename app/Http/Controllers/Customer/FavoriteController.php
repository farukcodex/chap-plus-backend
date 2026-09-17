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

    /**
     * List favorite restaurants for the authenticated user.
     */
    public function favoriteRestaurants(Request $request): JsonResponse
    {
        $user = $request->user();
        $profileLat = $user?->userProfile?->latitude;
        $profileLon = $user?->userProfile?->longitude;

        $targetLat = $request->filled('lat') 
            ? (float) $request->lat 
            : ($profileLat !== null ? (float) $profileLat : null);

        $targetLon = $request->filled('lng') 
            ? (float) $request->lng 
            : ($request->filled('lon') ? (float) $request->lon : ($profileLon !== null ? (float) $profileLon : null));

        $query = \App\Models\MerchantProfile::withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->whereHas('favoriteRestaurants', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });

        if ($targetLat !== null && $targetLon !== null) {
            $query->selectRaw(
                "merchant_profiles.*, (6371 * acos(
                    LEAST(1.0, GREATEST(-1.0, 
                        cos(radians(?)) * cos(radians(latitude)) 
                        * cos(radians(longitude) - radians(?)) 
                        + sin(radians(?)) * sin(radians(latitude))
                    ))
                )) AS distance_km",
                [$targetLat, $targetLon, $targetLat]
            )->orderBy('distance_km', 'asc');
        } else {
            $query->latest('id');
        }

        $perPage = (int) $request->input('per_page', 20);
        $restaurants = $query->paginate($perPage);

        // Append is_favorite = true to each record
        $restaurants->getCollection()->transform(function ($item) {
            $item->is_favorite = true;
            return $item;
        });

        return $this->apiSuccess('Favorite restaurants retrieved successfully', [
            'restaurants' => $restaurants
        ]);
    }

    /**
     * Toggle favorite status for a restaurant.
     */
    public function toggleRestaurant(Request $request, int $restaurantId): JsonResponse
    {
        $restaurant = \App\Models\MerchantProfile::whereHas('user.roles', function ($q) {
            $q->where('name', 'RESTAURANT_MERCHANT');
        })->find($restaurantId);

        if (!$restaurant) {
            return $this->apiError('Restaurant not found', 404, ['code' => 'RESTAURANT_NOT_FOUND']);
        }

        $userId = $request->user()->id;

        $favorite = \App\Models\FavoriteRestaurant::where('user_id', $userId)
            ->where('merchant_profile_id', $restaurantId)
            ->first();

        if ($favorite) {
            $favorite->delete();
            return $this->apiSuccess('Removed restaurant from favorites', ['is_favorite' => false]);
        }

        \App\Models\FavoriteRestaurant::create([
            'user_id' => $userId,
            'merchant_profile_id' => $restaurantId,
        ]);

        return $this->apiSuccess('Added restaurant to favorites', ['is_favorite' => true]);
    }
}
