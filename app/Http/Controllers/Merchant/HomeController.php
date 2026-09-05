<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\HotelBooking;
use App\Models\Hotel;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class HomeController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $merchant = $request->user()->merchantProfile;
        $isHotel = $request->user()->hasRole('HOTEL_MERCHANT');

        if (!$merchant) {
            return $this->apiError('Merchant profile not found', 404, ['code' => 'MERCHANT_NOT_FOUND']);
        }

        $month = $request->query('month', now()->month);
        $year = $request->query('year', now()->year);

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $lastMonthStartDate = $startDate->copy()->subMonth()->startOfMonth();
        $lastMonthEndDate = $lastMonthStartDate->copy()->endOfMonth();

        if ($isHotel) {
            return $this->getHotelDashboard($merchant, $startDate, $endDate, $lastMonthStartDate, $lastMonthEndDate);
        } else {
            return $this->getEcommerceDashboard($merchant, $startDate, $endDate, $lastMonthStartDate, $lastMonthEndDate);
        }
    }

    private function getEcommerceDashboard($merchant, $startDate, $endDate, $lastMonthStartDate, $lastMonthEndDate)
    {
        // 1. Total Sales (Current Month)
        $currentSales = Order::where('merchant_profile_id', $merchant->id)
            ->where('status', 'delivered')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('total_amount');

        // 2. Last Month Sales (For Growth %)
        $lastSales = Order::where('merchant_profile_id', $merchant->id)
            ->where('status', 'delivered')
            ->whereBetween('created_at', [$lastMonthStartDate, $lastMonthEndDate])
            ->sum('total_amount');

        $growth = 0;
        if ($lastSales > 0) {
            $growth = (($currentSales - $lastSales) / $lastSales) * 100;
        } elseif ($currentSales > 0) {
            $growth = 100;
        }

        // 3. Stats Counts
        $ordersCount = Order::where('merchant_profile_id', $merchant->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $productsCount = Product::where('merchant_profile_id', $merchant->id)->count();

        $customersCount = Order::where('merchant_profile_id', $merchant->id)
            ->distinct('user_id')
            ->count('user_id');

        // 4. Recent Orders (Latest 5)
        $recentOrders = Order::with(['items.product'])
            ->where('merchant_profile_id', $merchant->id)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($order) {
                $firstItem = $order->items->first();
                $productName = $firstItem ? $firstItem->product->name : 'Unknown Product';
                $productImage = $firstItem ? $firstItem->product->image : null;

                return [
                    'id' => $order->id,
                    'order_number' => '#ORD-' . str_pad($order->id, 5, '0', STR_PAD_LEFT),
                    'total_amount' => $order->total_amount,
                    'items_count' => $order->items->sum('quantity'),
                    'status' => $order->status,
                    'created_at' => $order->created_at,
                    'product_name' => $productName,
                    'product_image' => $productImage,
                ];
            });

        return $this->apiSuccess('Merchant dashboard retrieved', [
            'sales' => [
                'total' => round($currentSales, 2),
                'growth_percentage' => round($growth, 1),
                'is_positive' => $growth >= 0,
            ],
            'stats' => [
                'orders' => $ordersCount,
                'products' => $productsCount,
                'customers' => $customersCount,
            ],
            'recent_activity' => $recentOrders,
        ]);
    }

    private function getHotelDashboard($merchant, $startDate, $endDate, $lastMonthStartDate, $lastMonthEndDate)
    {
        // 1. Total Revenue (Current Month)
        $currentSales = HotelBooking::where('merchant_profile_id', $merchant->id)
            ->whereIn('status', ['checked_in', 'checked_out'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('total_price');

        // 2. Last Month Sales (For Growth %)
        $lastSales = HotelBooking::where('merchant_profile_id', $merchant->id)
            ->whereIn('status', ['checked_in', 'checked_out'])
            ->whereBetween('created_at', [$lastMonthStartDate, $lastMonthEndDate])
            ->sum('total_price');

        $growth = 0;
        if ($lastSales > 0) {
            $growth = (($currentSales - $lastSales) / $lastSales) * 100;
        } elseif ($currentSales > 0) {
            $growth = 100;
        }

        // 3. Stats Counts
        $bookingsCount = HotelBooking::where('merchant_profile_id', $merchant->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $propertiesCount = Hotel::where('merchant_profile_id', $merchant->id)->count();

        $guestsCount = HotelBooking::where('merchant_profile_id', $merchant->id)
            ->distinct('user_id')
            ->count('user_id');

        // 4. Recent Bookings (Latest 5)
        $recentBookings = HotelBooking::with(['hotel.images'])
            ->where('merchant_profile_id', $merchant->id)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($booking) {
                $primaryImage = $booking->hotel->images->where('is_primary', true)->first();
                return [
                    'id' => $booking->id,
                    'order_number' => '#BKG-' . str_pad($booking->id, 5, '0', STR_PAD_LEFT),
                    'total_amount' => $booking->total_price,
                    'items_count' => $booking->rooms_booked,
                    'status' => $booking->status,
                    'created_at' => $booking->created_at,
                    'product_name' => $booking->hotel->name,
                    'product_image' => $primaryImage ? $primaryImage->image_path : null,
                ];
            });

        return $this->apiSuccess('Merchant dashboard retrieved', [
            'sales' => [
                'total' => round($currentSales, 2),
                'growth_percentage' => round($growth, 1),
                'is_positive' => $growth >= 0,
            ],
            'stats' => [
                'bookings' => $bookingsCount,
                'properties' => $propertiesCount,
                'guests' => $guestsCount,
            ],
            'recent_activity' => $recentBookings,
        ]);
    }
}
