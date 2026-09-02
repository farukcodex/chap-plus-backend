<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $merchant = $user->merchantProfile;

        if (!$merchant) {
            return $this->apiError('Merchant profile not found', 404);
        }

        $year = $request->query('year', now()->year);

        // 1. Payout Balance
        $wallet = Wallet::firstOrCreate(['user_id' => $user->id]);

        // 2. Monthly Earnings (from Wallet Transactions type=credit for this user)
        // Note: It's better to fetch actual net earnings they received in their wallet.
        $monthlyEarnings = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthName = Carbon::create()->month($month)->format('M'); // Jan, Feb, etc.
            
            $earnings = WalletTransaction::where('wallet_id', $wallet->id)
                ->where('type', 'credit')
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->sum('amount');
                
            $monthlyEarnings[] = [
                'month' => $monthName,
                'total' => round($earnings, 2)
            ];
        }

        // 3. Stats (All Time)
        $ordersCount = Order::where('merchant_profile_id', $merchant->id)->count();
        $customersCount = Order::where('merchant_profile_id', $merchant->id)->distinct('user_id')->count('user_id');
        $avgOrderValue = Order::where('merchant_profile_id', $merchant->id)->avg('total_amount') ?? 0.00;

        // 4. Top Selling Products
        // Find top products by joining order_items
        $topProducts = Product::select('products.id', 'products.name', 'products.base_price', DB::raw('SUM(order_items.quantity) as total_sold'))
            ->join('order_items', 'products.id', '=', 'order_items.product_id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('products.merchant_profile_id', $merchant->id)
            ->where('orders.status', 'delivered')
            ->groupBy('products.id', 'products.name', 'products.base_price')
            ->orderByDesc('total_sold')
            ->take(5)
            ->get();
            
        // Load primary image for each product manually since we used a raw select/group by
        foreach ($topProducts as $product) {
            $primaryImage = \App\Models\ProductImage::where('product_id', $product->id)->where('is_primary', true)->first();
            $product->image = $primaryImage ? $primaryImage->image_path : null;
            // Also ensure total_sold is an integer in the response
            $product->total_sold = (int) $product->total_sold;
        }

        return $this->apiSuccess('Analytics retrieved successfully', [
            'payout_balance' => round($wallet->balance, 2),
            'currency' => $wallet->currency,
            'monthly_earnings' => $monthlyEarnings,
            'stats' => [
                'orders' => $ordersCount,
                'customers' => $customersCount,
                'avg_order_value' => round($avgOrderValue, 2),
            ],
            'top_selling_products' => $topProducts
        ]);
    }

    public function topProducts(Request $request): JsonResponse
    {
        $user = $request->user();
        $merchant = $user->merchantProfile;

        if (!$merchant) {
            return $this->apiError('Merchant profile not found', 404);
        }

        // Find all products by joining order_items, paginated
        $topProducts = Product::select('products.id', 'products.name', 'products.base_price', DB::raw('COALESCE(SUM(order_items.quantity), 0) as total_sold'))
            ->leftJoin('order_items', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('orders', function($join) {
                $join->on('order_items.order_id', '=', 'orders.id')
                     ->where('orders.status', '=', 'delivered');
            })
            ->where('products.merchant_profile_id', $merchant->id)
            ->groupBy('products.id', 'products.name', 'products.base_price')
            ->orderByDesc('total_sold')
            ->paginate(15);
            
        // Load primary image for each product manually
        $topProducts->getCollection()->transform(function ($product) {
            $primaryImage = \App\Models\ProductImage::where('product_id', $product->id)->where('is_primary', true)->first();
            $product->image = $primaryImage ? $primaryImage->image_path : null;
            $product->total_sold = (int) $product->total_sold;
            return $product;
        });

        return $this->apiSuccess('Top products retrieved successfully', [
            'top_products' => $topProducts
        ]);
    }
}
