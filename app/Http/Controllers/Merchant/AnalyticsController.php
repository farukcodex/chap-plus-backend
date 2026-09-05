<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\HotelBooking;
use App\Models\Hotel;
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
        $isHotel = $user->hasRole('HOTEL_MERCHANT');

        if (!$merchant) {
            return $this->apiError('Merchant profile not found', 404);
        }

        $year = $request->query('year', now()->year);

        // 1. Payout Balance
        $wallet = Wallet::firstOrCreate(['user_id' => $user->id]);

        // 2. Monthly Earnings (from Wallet Transactions type=credit for this user)
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

        if ($isHotel) {
            return $this->getHotelAnalytics($merchant, $wallet, $monthlyEarnings);
        } else {
            return $this->getEcommerceAnalytics($merchant, $wallet, $monthlyEarnings);
        }
    }

    private function getEcommerceAnalytics($merchant, $wallet, $monthlyEarnings)
    {
        // 3. Stats (All Time)
        $ordersCount = Order::where('merchant_profile_id', $merchant->id)->count();
        $customersCount = Order::where('merchant_profile_id', $merchant->id)->distinct('user_id')->count('user_id');
        $avgOrderValue = Order::where('merchant_profile_id', $merchant->id)->avg('total_amount') ?? 0.00;

        // 4. Top Selling Products
        $topProducts = Product::select('products.id', 'products.name', 'products.base_price', DB::raw('SUM(order_items.quantity) as total_sold'))
            ->join('order_items', 'products.id', '=', 'order_items.product_id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('products.merchant_profile_id', $merchant->id)
            ->where('orders.status', 'delivered')
            ->groupBy('products.id', 'products.name', 'products.base_price')
            ->orderByDesc('total_sold')
            ->take(5)
            ->get();

        foreach ($topProducts as $product) {
            $primaryImage = \App\Models\ProductImage::where('product_id', $product->id)->where('is_primary', true)->first();
            $product->image = $primaryImage ? $primaryImage->image_path : null;
            $product->total_sold = (int) $product->total_sold;
        }

        return $this->apiSuccess('Analytics retrieved successfully', [
            'balance' => round($wallet->balance, 2),
            'currency' => $wallet->currency,
            'monthly_earnings' => $monthlyEarnings,
            'stats' => [
                'orders' => $ordersCount,
                'customers' => $customersCount,
                'avg_order_value' => round($avgOrderValue, 2),
            ],
            'top_performers' => $topProducts
        ]);
    }

    private function getHotelAnalytics($merchant, $wallet, $monthlyEarnings)
    {
        // Calculate Pending Escrow Earnings (For Hotels)
        $merchantCommissionPercent = \App\Models\PlatformSetting::where('key', 'merchant_commission_percent')->value('value') ?? 10.00;
        
        $pendingHotelGross = HotelBooking::where('merchant_profile_id', $merchant->id)
            ->whereIn('status', ['paid', 'confirmed'])
            ->sum('total_price');
            
        $pendingHotelCommission = $pendingHotelGross * ($merchantCommissionPercent / 100);
        $pendingEscrowBalance = $pendingHotelGross - $pendingHotelCommission;

        // 3. Stats (All Time)
        $bookingsCount = HotelBooking::where('merchant_profile_id', $merchant->id)->count();
        $guestsCount = HotelBooking::where('merchant_profile_id', $merchant->id)->distinct('user_id')->count('user_id');
        $avgBookingValue = HotelBooking::where('merchant_profile_id', $merchant->id)->avg('total_price') ?? 0.00;

        // 4. Top Performing Hotels
        $topHotels = Hotel::select('hotels.id', 'hotels.name', 'hotels.price_per_night', DB::raw('COUNT(hotel_bookings.id) as total_sold'))
            ->join('hotel_bookings', 'hotels.id', '=', 'hotel_bookings.hotel_id')
            ->where('hotels.merchant_profile_id', $merchant->id)
            ->whereIn('hotel_bookings.status', ['checked_in', 'checked_out'])
            ->groupBy('hotels.id', 'hotels.name', 'hotels.price_per_night')
            ->orderByDesc('total_sold')
            ->take(5)
            ->get();

        foreach ($topHotels as $hotel) {
            $primaryImage = \App\Models\HotelImage::where('hotel_id', $hotel->id)->where('is_primary', true)->first();
            $hotel->image = $primaryImage ? $primaryImage->image_path : null;
            $hotel->total_sold = (int) $hotel->total_sold;
        }

        return $this->apiSuccess('Analytics retrieved successfully', [
            'balance' => round($wallet->balance, 2),
            'pending_escrow_balance' => round($pendingEscrowBalance, 2),
            'currency' => $wallet->currency,
            'monthly_earnings' => $monthlyEarnings,
            'stats' => [
                'bookings' => $bookingsCount,
                'guests' => $guestsCount,
                'avg_booking_value' => round($avgBookingValue, 2),
            ],
            'top_performers' => $topHotels
        ]);
    }

    public function topProducts(Request $request): JsonResponse
    {
        $user = $request->user();
        $merchant = $user->merchantProfile;
        $isHotel = $user->hasRole('HOTEL_MERCHANT');

        if (!$merchant) {
            return $this->apiError('Merchant profile not found', 404);
        }

        if ($isHotel) {
            return $this->getTopHotels($merchant);
        }

        // Find all products by joining order_items, paginated
        $topProducts = Product::select('products.id', 'products.name', 'products.base_price', DB::raw('COALESCE(SUM(order_items.quantity), 0) as total_sold'))
            ->leftJoin('order_items', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('orders', function ($join) {
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
            'top_performers' => $topProducts
        ]);
    }
    
    private function getTopHotels($merchant)
    {
        $topHotels = Hotel::select('hotels.id', 'hotels.name', 'hotels.price_per_night', DB::raw('COUNT(hotel_bookings.id) as total_sold'))
            ->leftJoin('hotel_bookings', function ($join) {
                $join->on('hotels.id', '=', 'hotel_bookings.hotel_id')
                    ->whereIn('hotel_bookings.status', ['checked_in', 'checked_out']);
            })
            ->where('hotels.merchant_profile_id', $merchant->id)
            ->groupBy('hotels.id', 'hotels.name', 'hotels.price_per_night')
            ->orderByDesc('total_sold')
            ->paginate(15);

        $topHotels->getCollection()->transform(function ($hotel) {
            $primaryImage = \App\Models\HotelImage::where('hotel_id', $hotel->id)->where('is_primary', true)->first();
            $hotel->image = $primaryImage ? $primaryImage->image_path : null;
            $hotel->total_sold = (int) $hotel->total_sold;
            return $hotel;
        });

        return $this->apiSuccess('Top hotels retrieved successfully', [
            'top_performers' => $topHotels
        ]);
    }
}
