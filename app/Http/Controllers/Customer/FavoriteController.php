<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Product;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $favorites = Favorite::with('product')
            ->where('user_id', $request->user()->id)
            ->get()
            ->map(function ($favorite) {
                $product = $favorite->product;
                // Add is_favorite boolean true for UI convenience
                $product->is_favorite = true;
                return $product;
            });

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
