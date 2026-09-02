<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class EcommerceController extends Controller
{
    use ApiResponseTrait;

    public function home(Request $request): JsonResponse
    {
        // Only fetch Main Categories (parent_id is null) but include their Subcategories
        $categories = ProductCategory::select('id', 'name', 'slug', 'parent_id')
            ->whereNull('parent_id')
            ->with(['subcategories' => function($query) {
                $query->select('id', 'name', 'slug', 'parent_id');
            }])
            ->get();
        
        $userCountry = $request->user()->userProfile->country ?? null;

        // Let's get the 10 latest active products for the home screen feed, filtered by user's country
        $featuredProducts = Product::with(['images', 'variants'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->where('is_active', true)
            ->whereHas('merchantProfile', function ($q) use ($userCountry) {
                if ($userCountry) {
                    $q->where('country', $userCountry);
                }
            })
            ->latest()
            ->take(10)
            ->get();

        $user = Auth::guard('sanctum')->user();
        $favoriteIds = $user ? \App\Models\Favorite::where('user_id', $user->id)->pluck('product_id')->toArray() : [];

        $featuredProducts->transform(function ($product) use ($favoriteIds) {
            $product->is_favorite = in_array($product->id, $favoriteIds);
            return $product;
        });

        return $this->apiSuccess('Home data retrieved', [
            'categories' => $categories,
            'featured_products' => $featuredProducts
        ]);
    }

    public function categories(Request $request): JsonResponse
    {
        $query = ProductCategory::select('id', 'name', 'slug', 'parent_id');

        // Only attach the nested subcategories array if we haven't explicitly turned it off
        if ($request->boolean('include_subcategories', true)) {
            $query->with(['subcategories' => function($q) {
                $q->select('id', 'name', 'slug', 'parent_id');
            }]);
        }

        // If they want to fetch a specific main category (e.g. ?slug=beauty) to see its subcategories
        if ($request->has('slug')) {
            $query->where('slug', $request->slug);
        } 
        // If they want to fetch subcategories directly by their parent's slug (e.g. ?parent_slug=beauty)
        elseif ($request->has('parent_slug')) {
            $query->whereHas('parent', function($q) use ($request) {
                $q->where('slug', $request->parent_slug);
            });
        } 
        // Default: return all main categories
        else {
            $query->whereNull('parent_id');
        }

        $categories = $query->get();

        return $this->apiSuccess('Categories retrieved', [
            'categories' => $categories
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $userCountry = $request->user()->userProfile->country ?? null;

        $query = Product::with(['images', 'variants'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->where('is_active', true)
            ->whereHas('merchantProfile', function ($q) use ($userCountry) {
                if ($userCountry) {
                    $q->where('country', $userCountry);
                }
            });

        // Filter by Merchant Store
        if ($request->has('merchant_profile_id')) {
            $query->where('merchant_profile_id', $request->merchant_profile_id);
        }

        // Filter by Category (Smart enough to handle Main Categories AND Subcategories)
        if ($request->has('category_id') || $request->has('category_slug')) {
            $categoryQuery = ProductCategory::query();
            
            if ($request->has('category_id')) {
                $categoryQuery->where('id', $request->category_id);
            } else {
                $categoryQuery->where('slug', $request->category_slug);
            }
            
            $category = $categoryQuery->first();
            
            if ($category) {
                // Grab the requested category ID AND all of its children's IDs
                $categoryIds = ProductCategory::where('parent_id', $category->id)
                    ->pluck('id')
                    ->push($category->id)
                    ->toArray();
                    
                $query->whereIn('category_id', $categoryIds);
            } else {
                // Force empty result if category doesn't exist
                $query->where('category_id', 0);
            }
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // Price filtering
        if ($request->has('min_price')) {
            $query->where('base_price', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('base_price', '<=', $request->max_price);
        }

        // Color filtering (queries the JSON 'attributes' column on variants)
        if ($request->has('color')) {
            $query->whereHas('variants', function($q) use ($request) {
                // E.g. where attributes->Color = 'Black'
                $q->where('attributes->Color', $request->color)
                  ->orWhere('attributes->color', $request->color);
            });
        }

        // Size filtering (queries the JSON 'attributes' column on variants)
        if ($request->has('size')) {
            $query->whereHas('variants', function($q) use ($request) {
                // E.g. where attributes->Size = 'XL'
                $q->where('attributes->Size', $request->size)
                  ->orWhere('attributes->size', $request->size);
            });
        }

        // Gender filtering (queries the JSON 'attributes' column on variants)
        if ($request->has('gender')) {
            $query->whereHas('variants', function($q) use ($request) {
                // E.g. where attributes->Gender = 'Men'
                $q->where('attributes->Gender', $request->gender)
                  ->orWhere('attributes->gender', $request->gender);
            });
        }

        // Filter by Highly Recommended
        if ($request->boolean('is_highly_recommended')) {
            $query->having('reviews_avg_rating', '>=', 4);
        }

        // Sorting
        if ($request->has('sort')) {
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
                    $query->latest();
                    break;
                default:
                    $query->latest();
                    break;
            }
        } else {
            // Default sort: if highly recommended, sort by rating, else newest
            if ($request->boolean('is_highly_recommended')) {
                $query->orderBy('reviews_avg_rating', 'desc');
            } else {
                $query->latest();
            }
        }

        $products = $query->paginate(20);

        $user = Auth::guard('sanctum')->user();
        $favoriteIds = $user ? \App\Models\Favorite::where('user_id', $user->id)->pluck('product_id')->toArray() : [];

        $products->getCollection()->transform(function ($product) use ($favoriteIds) {
            $product->is_favorite = in_array($product->id, $favoriteIds);
            return $product;
        });

        return $this->apiSuccess('Products retrieved', ['products' => $products]);
    }

    public function show(string $id): JsonResponse
    {
        $product = Product::with([
                'images', 
                'variants', 
                'category', 
                'merchantProfile', 
                'reviews.user.userProfile' // Load reviews and the reviewer's profile
            ])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->where('is_active', true)
            ->find($id);

        if (!$product) {
            return $this->apiError('Product not found or inactive', 404);
        }

        // 1. Parse Variants into frontend-friendly lists
        $availableColors = [];
        $availableSizes = [];
        
        foreach ($product->variants as $variant) {
            $attrs = $variant->attributes;
            if (is_array($attrs)) {
                if (isset($attrs['Color'])) $availableColors[] = $attrs['Color'];
                if (isset($attrs['color'])) $availableColors[] = $attrs['color'];
                
                if (isset($attrs['Size'])) $availableSizes[] = $attrs['Size'];
                if (isset($attrs['size'])) $availableSizes[] = $attrs['size'];
            }
        }

        // Remove duplicates and re-index array
        $product->available_colors = array_values(array_unique($availableColors));
        $product->available_sizes = array_values(array_unique($availableSizes));

        // 2. Fetch "You Might Like" related products
        $relatedProducts = Product::with(['images', 'variants'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->where('is_active', true)
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->inRandomOrder()
            ->take(4)
            ->get();

        $user = Auth::guard('sanctum')->user();
        if ($user) {
            $product->is_favorite = \App\Models\Favorite::where('user_id', $user->id)
                ->where('product_id', $product->id)
                ->exists();
                
            $favIds = \App\Models\Favorite::where('user_id', $user->id)
                ->whereIn('product_id', $relatedProducts->pluck('id'))
                ->pluck('product_id')
                ->toArray();
                
            $relatedProducts->transform(function($rp) use ($favIds) {
                $rp->is_favorite = in_array($rp->id, $favIds);
                return $rp;
            });
        } else {
            $product->is_favorite = false;
            $relatedProducts->transform(function($rp) {
                $rp->is_favorite = false;
                return $rp;
            });
        }

        return $this->apiSuccess('Product details retrieved', [
            'product' => $product,
            'related_products' => $relatedProducts
        ]);
    }

    public function addReview(Request $request, string $id): JsonResponse
    {
        $product = Product::find($id);

        if (!$product) {
            return $this->apiError('Product not found', 404);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000'
        ]);

        // Note: For a production app, you might want to verify they actually purchased the product first.
        // For now, we will just create or update the review.
        
        $review = \App\Models\ProductReview::updateOrCreate(
            ['product_id' => $product->id, 'user_id' => $request->user()->id],
            ['rating' => $validated['rating'], 'comment' => $validated['comment'] ?? null]
        );

        return $this->apiSuccess('Review submitted successfully', ['review' => $review], 201);
    }

    public function stores(Request $request): JsonResponse
    {
        $query = \App\Models\MerchantProfile::withAvg('reviews', 'rating')
            ->withCount('reviews');

        if ($request->has('lat') && $request->has('lng')) {
            $lat = $request->lat;
            $lng = $request->lng;
            // Haversine formula for distance in kilometers
            $query->selectRaw("merchant_profiles.*, ( 6371 * acos( cos( radians(?) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(?) ) + sin( radians(?) ) * sin( radians( latitude ) ) ) ) AS distance_km", [$lat, $lng, $lat])
                  ->orderBy('distance_km');
        } else {
            $query->select('merchant_profiles.*');
        }

        $stores = $query->paginate(20);

        return $this->apiSuccess('Stores retrieved', ['stores' => $stores]);
    }

    public function storeDetails(Request $request, string $id): JsonResponse
    {
        $query = \App\Models\MerchantProfile::withAvg('reviews', 'rating')
            ->withCount('reviews');

        if ($request->has('lat') && $request->has('lng')) {
            $lat = $request->lat;
            $lng = $request->lng;
            $query->selectRaw("merchant_profiles.*, ( 6371 * acos( cos( radians(?) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(?) ) + sin( radians(?) ) * sin( radians( latitude ) ) ) ) AS distance_km", [$lat, $lng, $lat]);
        } else {
            $query->select('merchant_profiles.*');
        }

        $store = $query->find($id);

        if (!$store) {
            return $this->apiError('Store not found', 404);
        }

        // Fetch highly recommended products for this specific store
        $highlyRecommended = Product::with(['images', 'variants'])
            ->withAvg('reviews', 'rating')
            ->where('is_active', true)
            ->where('merchant_profile_id', $store->id)
            ->having('reviews_avg_rating', '>=', 4)
            ->orderBy('reviews_avg_rating', 'desc')
            ->take(5)
            ->get();
            
        // Fallback if they don't have rated products yet
        if ($highlyRecommended->isEmpty()) {
            $highlyRecommended = Product::with(['images', 'variants'])
                ->withAvg('reviews', 'rating')
                ->where('is_active', true)
                ->where('merchant_profile_id', $store->id)
                ->inRandomOrder()
                ->take(5)
                ->get();
        }

        return $this->apiSuccess('Store details retrieved', [
            'store' => $store,
            'highly_recommended' => $highlyRecommended
        ]);
    }
}
