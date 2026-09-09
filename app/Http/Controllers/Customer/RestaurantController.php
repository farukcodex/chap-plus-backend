<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\MerchantProfile;
use App\Models\ProductReview;
use App\Models\Favorite;
use App\Http\Resources\Customer\RestaurantFoodResource;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class RestaurantController extends Controller
{
    use ApiResponseTrait;

    /**
     * Restaurant Home Screen: Cuisines, Featured Foods, and Popular Restaurants.
     */
    public function home(Request $request): JsonResponse
    {
        // 1. Fetch Main Cuisines / Restaurant Categories
        $cuisines = ProductCategory::select('id', 'name', 'slug', 'parent_id', 'type')
            ->whereNull('parent_id')
            ->whereNull('merchant_profile_id')
            ->where('type', 'restaurant')
            ->with(['subcategories' => function ($query) {
                $query->select('id', 'name', 'slug', 'parent_id', 'type')
                      ->whereNull('merchant_profile_id')
                      ->where('type', 'restaurant');
            }])
            ->get();

        // 2. Fetch Featured Foods
        $foodsQuery = Product::with(['images', 'variants', 'category.parent', 'merchantProfile'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->where('is_active', true)
            ->whereHas('merchantProfile.user.roles', function ($r) {
                $r->where('name', 'RESTAURANT_MERCHANT');
            });

        // 3. Fetch Featured Restaurants
        $restaurantsQuery = MerchantProfile::withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->whereHas('user.roles', function ($q) {
                $q->where('name', 'RESTAURANT_MERCHANT');
            });

        $userCountry = $request->user()?->userProfile?->country ?? null;
        $country = $request->query('country', $userCountry);
        if ($country && $country !== 'all') {
            $hasMerchants = MerchantProfile::where('country', $country)
                ->whereHas('user.roles', fn($r) => $r->where('name', 'RESTAURANT_MERCHANT'))
                ->exists();
            if ($hasMerchants) {
                $foodsQuery->whereHas('merchantProfile', fn($q) => $q->where('country', $country));
                $restaurantsQuery->where('country', $country);
            }
        }

        $featuredFoods = $foodsQuery->latest()->take(10)->get();

        $user = Auth::guard('sanctum')->user();
        $favoriteIds = $user ? Favorite::where('user_id', $user->id)->pluck('product_id')->toArray() : [];

        $transformedFoods = $featuredFoods->map(function ($food) use ($favoriteIds, $request) {
            $food->is_favorite = in_array($food->id, $favoriteIds);
            return (new RestaurantFoodResource($food))->toArray($request);
        });

        if ($request->filled('lat') && $request->filled('lng')) {
            $lat = $request->lat;
            $lng = $request->lng;
            $restaurantsQuery->selectRaw("merchant_profiles.*, ( 6371 * acos( cos( radians(?) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(?) ) + sin( radians(?) ) * sin( radians( latitude ) ) ) ) AS distance_km", [$lat, $lng, $lat])
                ->orderBy('distance_km');
        } else {
            $restaurantsQuery->select('merchant_profiles.*');
        }

        $featuredRestaurants = $restaurantsQuery->take(10)->get();

        return $this->apiSuccess('Restaurant home data retrieved', [
            'cuisines' => $cuisines,
            'featured_foods' => $transformedFoods,
            'featured_restaurants' => $featuredRestaurants,
        ]);
    }

    /**
     * Cuisines / Restaurant Categories listing.
     */
    public function categories(Request $request): JsonResponse
    {
        $query = ProductCategory::select('id', 'name', 'slug', 'parent_id', 'type')
            ->whereNull('merchant_profile_id')
            ->where('type', 'restaurant');

        if ($request->boolean('include_subcategories', true)) {
            $query->with(['subcategories' => function ($q) {
                $q->select('id', 'name', 'slug', 'parent_id', 'type')
                  ->whereNull('merchant_profile_id')
                  ->where('type', 'restaurant');
            }]);
        }

        if ($request->filled('slug')) {
            $query->where('slug', $request->slug);
        } elseif ($request->filled('parent_slug')) {
            $query->whereHas('parent', function ($q) use ($request) {
                $q->where('slug', $request->parent_slug);
            });
        } else {
            $query->whereNull('parent_id');
        }

        $cuisines = $query->get();

        return $this->apiSuccess('Restaurant cuisines retrieved', [
            'cuisines' => $cuisines,
        ]);
    }

    /**
     * List nearby restaurants with distance, reviews, and search.
     */
    public function restaurants(Request $request): JsonResponse
    {
        $query = MerchantProfile::withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->whereHas('user.roles', function ($q) {
                $q->where('name', 'RESTAURANT_MERCHANT');
            });

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('business_name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('lat') && $request->filled('lng')) {
            $lat = $request->lat;
            $lng = $request->lng;
            $query->selectRaw("merchant_profiles.*, ( 6371 * acos( cos( radians(?) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(?) ) + sin( radians(?) ) * sin( radians( latitude ) ) ) ) AS distance_km", [$lat, $lng, $lat])
                  ->orderBy('distance_km');
        } else {
            $query->select('merchant_profiles.*');
        }

        $restaurants = $query->paginate(20);

        return $this->apiSuccess('Restaurants retrieved', ['restaurants' => $restaurants]);
    }

    /**
     * Restaurant Details: Profile, Distance, Menu, and Recommended Dishes.
     */
    public function restaurantDetails(Request $request, string $id): JsonResponse
    {
        $query = MerchantProfile::withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->whereHas('user.roles', function ($q) {
                $q->where('name', 'RESTAURANT_MERCHANT');
            });

        if ($request->has('lat') && $request->has('lng')) {
            $lat = $request->lat;
            $lng = $request->lng;
            $query->selectRaw("merchant_profiles.*, ( 6371 * acos( cos( radians(?) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(?) ) + sin( radians(?) ) * sin( radians( latitude ) ) ) ) AS distance_km", [$lat, $lng, $lat]);
        } else {
            $query->select('merchant_profiles.*');
        }

        $restaurant = $query->find($id);

        if (!$restaurant) {
            return $this->apiError('Restaurant not found', 404);
        }

        // Fetch highly recommended / top-rated foods
        $highlyRecommended = Product::with(['images', 'variants', 'category.parent', 'merchantProfile'])
            ->withAvg('reviews', 'rating')
            ->where('is_active', true)
            ->where('merchant_profile_id', $restaurant->id)
            ->having('reviews_avg_rating', '>=', 4)
            ->orderBy('reviews_avg_rating', 'desc')
            ->take(5)
            ->get();

        if ($highlyRecommended->isEmpty()) {
            $highlyRecommended = Product::with(['images', 'variants', 'category.parent', 'merchantProfile'])
                ->withAvg('reviews', 'rating')
                ->where('is_active', true)
                ->where('merchant_profile_id', $restaurant->id)
                ->inRandomOrder()
                ->take(5)
                ->get();
        }

        // Fetch full restaurant menu items
        $menuItems = Product::with(['images', 'variants', 'category.parent', 'merchantProfile'])
            ->where('is_active', true)
            ->where('merchant_profile_id', $restaurant->id)
            ->latest()
            ->get();

        $user = Auth::guard('sanctum')->user();
        $favoriteIds = $user ? Favorite::where('user_id', $user->id)->pluck('product_id')->toArray() : [];

        $transformedRecommended = $highlyRecommended->map(function ($food) use ($favoriteIds, $request) {
            $food->is_favorite = in_array($food->id, $favoriteIds);
            return (new RestaurantFoodResource($food))->toArray($request);
        });

        $transformedMenu = $menuItems->map(function ($food) use ($favoriteIds, $request) {
            $food->is_favorite = in_array($food->id, $favoriteIds);
            return (new RestaurantFoodResource($food))->toArray($request);
        });

        return $this->apiSuccess('Restaurant details retrieved', [
            'restaurant' => $restaurant,
            'highly_recommended' => $transformedRecommended,
            'menu' => $transformedMenu,
        ]);
    }

    /**
     * Browse food items with filters (category, search, price, rating).
     */
    public function foods(Request $request): JsonResponse
    {
        $query = Product::with(['images', 'variants', 'category.parent', 'merchantProfile'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->where('is_active', true)
            ->whereHas('merchantProfile.user.roles', function ($r) {
                $r->where('name', 'RESTAURANT_MERCHANT');
            });

        // Country filtering with graceful fallback
        $userCountry = $request->user()?->userProfile?->country ?? null;
        $country = $request->query('country', $userCountry);
        if ($country && $country !== 'all') {
            $hasMerchants = MerchantProfile::where('country', $country)
                ->whereHas('user.roles', fn($r) => $r->where('name', 'RESTAURANT_MERCHANT'))
                ->exists();
            if ($hasMerchants) {
                $query->whereHas('merchantProfile', fn($q) => $q->where('country', $country));
            }
        }

        // Filter by Restaurant
        if ($request->filled('merchant_profile_id')) {
            $query->where('merchant_profile_id', $request->merchant_profile_id);
        }

        // Filter by Category / Cuisine
        if ($request->filled('category_id') || $request->filled('category_slug')) {
            $categoryQuery = ProductCategory::query();

            if ($request->filled('category_id')) {
                $categoryQuery->where('id', $request->category_id);
            } else {
                $categoryQuery->where('slug', $request->category_slug);
            }

            $category = $categoryQuery->first();

            if ($category) {
                $categoryIds = ProductCategory::where('parent_id', $category->id)
                    ->pluck('id')
                    ->push($category->id)
                    ->toArray();

                $query->whereIn('category_id', $categoryIds);
            } else {
                $query->where('category_id', 0);
            }
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // Price filtering
        if ($request->filled('min_price')) {
            $query->where('base_price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('base_price', '<=', $request->max_price);
        }

        // Highly recommended
        if ($request->boolean('is_highly_recommended')) {
            $query->having('reviews_avg_rating', '>=', 4);
        }

        // Sorting
        if ($request->filled('sort')) {
            switch ($request->sort) {
                case 'price_asc':
                    $query->orderBy('base_price', 'asc');
                    break;
                case 'price_desc':
                    $query->orderBy('base_price', 'desc');
                    break;
                case 'rating_desc':
                    $query->orderBy('reviews_avg_rating', 'desc');
                    break;
                case 'newest':
                default:
                    $query->latest();
                    break;
            }
        } else {
            if ($request->boolean('is_highly_recommended')) {
                $query->orderBy('reviews_avg_rating', 'desc');
            } else {
                $query->latest();
            }
        }

        $foods = $query->paginate(20);

        $user = Auth::guard('sanctum')->user();
        $favoriteIds = $user ? Favorite::where('user_id', $user->id)->pluck('product_id')->toArray() : [];

        $foods->through(function ($food) use ($favoriteIds, $request) {
            $food->is_favorite = in_array($food->id, $favoriteIds);
            return (new RestaurantFoodResource($food))->toArray($request);
        });

        return $this->apiSuccess('Foods retrieved', ['foods' => $foods]);
    }

    /**
     * Show food item details, options/variants, and reviews.
     */
    public function showFood(Request $request, string $id): JsonResponse
    {
        $food = Product::with([
                'images',
                'variants',
                'category.parent',
                'merchantProfile',
                'reviews.user',
            ])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->where('is_active', true)
            ->whereHas('merchantProfile.user.roles', function ($r) {
                $r->where('name', 'RESTAURANT_MERCHANT');
            })
            ->find($id);

        if (!$food) {
            return $this->apiError('Food item not found or inactive', 404);
        }

        // Related foods from the same restaurant or category
        $relatedFoods = Product::with(['images', 'variants', 'category.parent', 'merchantProfile'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->where('is_active', true)
            ->where('merchant_profile_id', $food->merchant_profile_id)
            ->where('id', '!=', $food->id)
            ->inRandomOrder()
            ->take(4)
            ->get();

        $user = Auth::guard('sanctum')->user();
        $favIds = $user ? Favorite::where('user_id', $user->id)->pluck('product_id')->toArray() : [];

        $food->is_favorite = in_array($food->id, $favIds);
        $foodData = (new RestaurantFoodResource($food))->toArray($request);

        $transformedRelated = $relatedFoods->map(function ($rf) use ($favIds, $request) {
            $rf->is_favorite = in_array($rf->id, $favIds);
            return (new RestaurantFoodResource($rf))->toArray($request);
        });

        return $this->apiSuccess('Food details retrieved', [
            'food' => $foodData,
            'related_foods' => $transformedRelated,
        ]);
    }

    /**
     * Submit a review for a food item.
     */
    public function addReview(Request $request, string $id): JsonResponse
    {
        $food = Product::whereHas('merchantProfile.user.roles', function ($r) {
            $r->where('name', 'RESTAURANT_MERCHANT');
        })->find($id);

        if (!$food) {
            return $this->apiError('Food item not found', 404);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $review = ProductReview::updateOrCreate(
            ['product_id' => $food->id, 'user_id' => $request->user()->id],
            ['rating' => $validated['rating'], 'comment' => $validated['comment'] ?? null]
        );

        return $this->apiSuccess('Food review submitted successfully', ['review' => $review], 201);
    }
}
