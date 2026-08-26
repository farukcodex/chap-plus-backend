<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class EcommerceController extends Controller
{
    use ApiResponseTrait;

    public function home(Request $request): JsonResponse
    {
        $categories = ProductCategory::select('id', 'name', 'slug', 'icon_url')->get();
        
        $userCountry = $request->user()->userProfile->country ?? null;

        // Let's get the 10 latest active products for the home screen feed, filtered by user's country
        $featuredProducts = Product::with(['images', 'variants'])
            ->where('is_active', true)
            ->whereHas('merchantProfile', function ($q) use ($userCountry) {
                if ($userCountry) {
                    $q->where('country', $userCountry);
                }
            })
            ->latest()
            ->take(10)
            ->get();

        return $this->apiSuccess('Home data retrieved', [
            'categories' => $categories,
            'featured_products' => $featuredProducts
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $userCountry = $request->user()->userProfile->country ?? null;

        $query = Product::with(['images', 'variants'])->where('is_active', true)
            ->whereHas('merchantProfile', function ($q) use ($userCountry) {
                if ($userCountry) {
                    $q->where('country', $userCountry);
                }
            });

        // Filter by Category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        } elseif ($request->has('category_slug')) {
            $query->whereHas('category', function($q) use ($request) {
                $q->where('slug', $request->category_slug);
            });
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

        // Sorting
        if ($request->has('sort')) {
            switch ($request->sort) {
                case 'price_asc':
                    $query->orderBy('base_price', 'asc');
                    break;
                case 'price_desc':
                    $query->orderBy('base_price', 'desc');
                    break;
                case 'newest':
                    $query->latest();
                    break;
                default:
                    $query->latest();
                    break;
            }
        } else {
            $query->latest();
        }

        $products = $query->paginate(20);

        return $this->apiSuccess('Products retrieved', ['products' => $products]);
    }

    public function show(string $id): JsonResponse
    {
        $product = Product::with(['images', 'variants', 'category', 'merchantProfile'])
            ->where('is_active', true)
            ->find($id);

        if (!$product) {
            return $this->apiError('Product not found or inactive', 404);
        }

        return $this->apiSuccess('Product details retrieved', ['product' => $product]);
    }
}
