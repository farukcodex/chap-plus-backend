<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Exception;

class ProductController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get products for the authenticated merchant.
     */
    public function index(Request $request): JsonResponse
    {
        $merchantProfile = $request->user()->merchantProfile;

        if (!$merchantProfile) {
            return $this->apiError('Merchant profile not found', 404);
        }

        $products = Product::where('merchant_profile_id', $merchantProfile->id)
            ->with(['images' => function($query) {
                $query->where('is_primary', true); // Only load primary image for the list
            }])
            ->get();

        return $this->apiSuccess('Products retrieved successfully', [
            'products' => $products
        ]);
    }

    /**
     * Store a new product along with variants and images.
     */
    public function store(Request $request): JsonResponse
    {
        $merchantProfile = $request->user()->merchantProfile;
        if (!$merchantProfile) {
            return $this->apiError('Merchant profile not found', 404);
        }

        // EMERGENCY DEBUG: If you are getting validation errors, let's see what Laravel actually sees.
        // Uncomment the line below to dump everything Laravel is receiving!
        // return response()->json(['data' => $request->all(), 'method' => $request->method(), 'headers' => $request->headers->all()]);

        $validated = $request->validate([
            'category_id' => 'required|exists:product_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'base_price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'unit_type' => 'required|string|max:50',
            'weight_kg' => 'nullable|numeric|min:0',
            'has_variants' => 'required|boolean',
            'is_active' => 'required|boolean',
            
            // Images
            'images' => 'nullable|array',
            'images.*' => 'image|max:5120',
            
            // Variants (can be just one variant if has_variants is false)
            'variants' => 'required|array',
            'variants.*.sku' => 'nullable|string|max:100',
            'variants.*.attributes' => 'nullable|array', // e.g. {"Size": "L"}
            'variants.*.price_adjustment' => 'nullable|numeric',
            'variants.*.stock_quantity' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            // 1. Create Product
            $product = Product::create([
                'merchant_profile_id' => $merchantProfile->id,
                'category_id' => $validated['category_id'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'base_price' => $validated['base_price'],
                'discount_price' => $validated['discount_price'] ?? null,
                'unit_type' => $validated['unit_type'],
                'weight_kg' => $validated['weight_kg'] ?? null,
                'has_variants' => $validated['has_variants'],
                'is_active' => $validated['is_active'],
            ]);

            // 2. Handle Variants
            foreach ($validated['variants'] as $v) {
                ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $v['sku'] ?? null,
                    'attributes' => $v['attributes'] ?? null,
                    'price_adjustment' => $v['price_adjustment'] ?? 0.00,
                    'stock_quantity' => $v['stock_quantity'],
                ]);
            }

            // 3. Handle Images
            if ($request->hasFile('images')) {
                $isPrimary = true; // Make the first uploaded image primary
                foreach ($request->file('images') as $image) {
                    $path = $image->store('product_images', 'public');
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $path,
                        'is_primary' => $isPrimary
                    ]);
                    $isPrimary = false; // Only first is true
                }
            }

            DB::commit();

            return $this->apiSuccess('Product created successfully', [
                'product_id' => $product->id
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            return $this->apiError('Failed to create product', 500, ['error' => $e->getMessage()]);
        }
    }

    /**
     * Display a specific product with all details.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $merchantProfile = $request->user()->merchantProfile;

        $product = Product::where('merchant_profile_id', $merchantProfile->id)
            ->with(['images', 'variants'])
            ->find($id);

        if (!$product) {
            return $this->apiError('Product not found', 404);
        }

        return $this->apiSuccess('Product retrieved successfully', [
            'product' => $product
        ]);
    }

    /**
     * Update the product status (fast toggle).
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $merchantProfile = $request->user()->merchantProfile;

        $validated = $request->validate([
            'is_active' => 'required|boolean'
        ]);

        $product = Product::where('merchant_profile_id', $merchantProfile->id)->find($id);

        if (!$product) {
            return $this->apiError('Product not found', 404);
        }

        $product->update(['is_active' => $validated['is_active']]);

        return $this->apiSuccess('Product status updated');
    }

    /**
     * Get all product categories for the dropdown.
     */
    public function getCategories(): JsonResponse
    {
        $categories = \App\Models\ProductCategory::select('id', 'name', 'slug', 'icon_url')->get();
        return $this->apiSuccess('Categories retrieved successfully', [
            'categories' => $categories
        ]);
    }
}
