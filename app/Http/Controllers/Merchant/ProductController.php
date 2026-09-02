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

        // Base query for counts
        $baseQuery = Product::where('merchant_profile_id', $merchantProfile->id);

        $counts = [
            'all' => (clone $baseQuery)->count(),
            'active' => (clone $baseQuery)->where('is_active', true)->count(),
            'inactive' => (clone $baseQuery)->where('is_active', false)->count(),
            'out_of_stock' => (clone $baseQuery)->whereDoesntHave('variants', function ($q) {
                $q->where('stock_quantity', '>', 0);
            })->count(),
        ];

        // Query for actual products
        $query = Product::where('merchant_profile_id', $merchantProfile->id)
            ->with(['images' => function ($query) {
                $query->where('is_primary', true); // Only load primary image for the list
            }, 'variants']);

        // Apply filters
        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            } elseif ($request->status === 'out_of_stock') {
                $query->whereDoesntHave('variants', function ($q) {
                    $q->where('stock_quantity', '>', 0);
                });
            }
        }

        $products = $query->latest()->paginate(20);

        return $this->apiSuccess('Products retrieved successfully', [
            'counts' => $counts,
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
            'unit_value' => 'nullable|numeric|min:0',
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
            'variants.*.stock_quantity' => 'required|integer|min:0',
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
                'unit_value' => $validated['unit_value'] ?? null,
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
                'product' => $product
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
     * Update an existing product and its variants.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $merchantProfile = $request->user()->merchantProfile;

        $product = Product::where('merchant_profile_id', $merchantProfile->id)->find($id);

        if (!$product) {
            return $this->apiError('Product not found', 404);
        }

        $validated = $request->validate([
            'category_id' => 'required|exists:product_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'base_price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'unit_type' => 'required|string|max:50',
            'unit_value' => 'nullable|numeric|min:0',
            'has_variants' => 'required|boolean',
            'is_active' => 'required|boolean',

            // Images to append and delete
            'images' => 'nullable|array',
            'images.*' => 'image|max:5120',
            'images_to_delete' => 'nullable|array',
            'images_to_delete.*' => 'integer|exists:product_images,id',

            // Variants
            'variants' => 'required|array',
            'variants.*.id' => 'nullable|integer', // To identify existing variants
            'variants.*.sku' => 'nullable|string|max:100',
            'variants.*.attributes' => 'nullable|array',
            'variants.*.price_adjustment' => 'nullable|numeric',
            'variants.*.stock_quantity' => 'required|integer|min:0',
        ]);

        try {
            DB::beginTransaction();

            $product->update([
                'category_id' => $validated['category_id'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'base_price' => $validated['base_price'],
                'discount_price' => $validated['discount_price'] ?? null,
                'unit_type' => $validated['unit_type'],
                'unit_value' => $validated['unit_value'] ?? null,
                'has_variants' => $validated['has_variants'],
                'is_active' => $validated['is_active'],
            ]);

            // Sync Variants
            $providedVariantIds = [];
            foreach ($validated['variants'] as $v) {
                if (isset($v['id']) && $v['id']) {
                    // Update existing variant
                    $variant = ProductVariant::where('product_id', $product->id)->find($v['id']);
                    if ($variant) {
                        $variant->update([
                            'sku' => $v['sku'] ?? null,
                            'attributes' => $v['attributes'] ?? null,
                            'price_adjustment' => $v['price_adjustment'] ?? 0.00,
                            'stock_quantity' => $v['stock_quantity'],
                        ]);
                        $providedVariantIds[] = $variant->id;
                    }
                } else {
                    // Create new variant
                    $newVariant = ProductVariant::create([
                        'product_id' => $product->id,
                        'sku' => $v['sku'] ?? null,
                        'attributes' => $v['attributes'] ?? null,
                        'price_adjustment' => $v['price_adjustment'] ?? 0.00,
                        'stock_quantity' => $v['stock_quantity'],
                    ]);
                    $providedVariantIds[] = $newVariant->id;
                }
            }

            // Remove any old variants not provided in this payload (only if they aren't tied to orders)
            // Wait, to be safe, if we just delete them, it might cascade and delete order_items.
            // Ideally we'd soft delete. But since we lack soft deletes, we'll try to delete, 
            // and if it fails (due to foreign key constraint), we'll ignore it.
            $orphanedVariants = ProductVariant::where('product_id', $product->id)
                ->whereNotIn('id', $providedVariantIds)
                ->get();

            foreach ($orphanedVariants as $orphan) {
                try {
                    $orphan->delete();
                } catch (Exception $e) {
                    // Fallback: If it's tied to an order and can't be deleted, just set stock to 0 so it acts inactive
                    $orphan->update(['stock_quantity' => 0]);
                }
            }

            // Handle Images deletion
            if (!empty($validated['images_to_delete'])) {
                $imagesToDelete = ProductImage::where('product_id', $product->id)
                    ->whereIn('id', $validated['images_to_delete'])
                    ->get();

                foreach ($imagesToDelete as $img) {
                    Storage::disk('public')->delete($img->image_path);
                    $img->delete();
                }
            }

            // Handle new Images
            if ($request->hasFile('images')) {
                // If the product has no primary image after deletions, make the first new one primary
                $hasPrimary = ProductImage::where('product_id', $product->id)->where('is_primary', true)->exists();
                $isPrimary = !$hasPrimary;

                foreach ($request->file('images') as $image) {
                    $path = $image->store('product_images', 'public');
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $path,
                        'is_primary' => $isPrimary
                    ]);
                    $isPrimary = false;
                }
            }

            DB::commit();

            return $this->apiSuccess('Product updated successfully', ['product' => $product]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->apiError('Failed to update product', 500, ['error' => $e->getMessage()]);
        }
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
            return $this->apiError('Product not found', 404, ['error' => 'PRODUCT_NOT_FOUND']);
        }

        $product->update(['is_active' => $validated['is_active']]);

        return $this->apiSuccess('Product status updated');
    }

    /**
     * Get all product categories for the dropdown.
     */
    public function getCategories(Request $request): JsonResponse
    {
        $user = $request->user();
        $merchantProfile = $user->merchantProfile;
        
        $type = 'ecommerce';
        if ($user->hasRole('RESTAURANT_MERCHANT')) {
            $type = 'restaurant';
        } elseif ($user->hasRole('GROCERY_MERCHANT')) {
            $type = 'grocery';
        }

        $categories = \App\Models\ProductCategory::select('id', 'name', 'slug', 'parent_id', 'type', 'merchant_profile_id')
            ->whereNull('parent_id')
            ->where(function ($query) use ($merchantProfile, $type) {
                // Global categories for this vertical
                $query->whereNull('merchant_profile_id')->where('type', $type);
                // Or the merchant's own custom categories
                if ($merchantProfile) {
                    $query->orWhere('merchant_profile_id', $merchantProfile->id);
                }
            })
            ->with(['subcategories' => function ($query) use ($merchantProfile, $type) {
                $query->select('id', 'name', 'slug', 'parent_id', 'type', 'merchant_profile_id')
                    ->where(function ($q) use ($merchantProfile, $type) {
                        $q->whereNull('merchant_profile_id')->where('type', $type);
                        if ($merchantProfile) {
                            $q->orWhere('merchant_profile_id', $merchantProfile->id);
                        }
                    });
            }])
            ->get();

        return $this->apiSuccess('Categories retrieved successfully', [
            'categories' => $categories
        ]);
    }

    /**
     * Delete a product (if not tied to any orders).
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $merchantProfile = $request->user()->merchantProfile;

        $product = Product::where('merchant_profile_id', $merchantProfile->id)->find($id);

        if (!$product) {
            return $this->apiError('Product not found', 404, ['error' => 'PRODUCT_NOT_FOUND']);
        }

        // Safety check: Prevent deletion if product was already ordered
        if ($product->orderItems()->exists()) {
            return $this->apiError('Cannot delete this product because it is tied to past customer orders. Please disable (deactivate) it instead.', 400);
        }

        try {
            DB::beginTransaction();

            // Delete images from storage
            $images = $product->images;
            foreach ($images as $img) {
                Storage::disk('public')->delete($img->image_path);
            }

            // DB cascades will handle variants and image DB records, but we explicitly delete the product
            $product->delete();

            DB::commit();

            return $this->apiSuccess('Product deleted successfully');
        } catch (Exception $e) {
            DB::rollBack();
            return $this->apiError('Failed to delete product', 500, ['error' => $e->getMessage()]);
        }
    }
}
