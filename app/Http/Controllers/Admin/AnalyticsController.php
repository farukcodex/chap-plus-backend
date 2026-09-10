<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusBooking;
use App\Models\HotelBooking;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PlatformSetting;
use App\Models\ProductCategory;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    use ApiResponseTrait;

    /**
     * Palette of distinct UI colors for categories matching the dashboard design.
     */
    private const CATEGORY_COLORS = [
        '#FF3D00', // Bright Orange-Red (Electronics)
        '#2CD9C5', // Cyan-Teal (Accessories)
        '#FFA726', // Amber-Orange (Clothing)
        '#4CAF50', // Green (Footwear)
        '#7E57C2', // Purple (Beauty)
        '#00BCD4', // Cyan
        '#E91E63', // Pink
        '#3F51B5', // Indigo
        '#8BC34A', // Light Green
        '#FF9800', // Deep Orange
    ];

    /**
     * Endpoint 1: Top 5 KPI Stat Cards
     */
    public function overview(): JsonResponse
    {
        $commissionRate = (float) (PlatformSetting::where('key', 'merchant_commission_percent')->value('value') ?? 11.00);
        $currency = (string) (PlatformSetting::where('key', 'currency')->value('value') ?? 'KES');

        // 1. Total Platform Lifetime Earnings
        $ordersEarning = (float) Order::where('status', 'delivered')->sum('admin_commission');
        $busEarning = (float) BusBooking::where('status', 'paid')->sum('total_price') * ($commissionRate / 100);
        $hotelEarning = (float) HotelBooking::whereIn('status', ['paid', 'confirmed', 'checked_in', 'checked_out'])->sum('total_price') * ($commissionRate / 100);

        $totalEarning = round($ordersEarning + $busEarning + $hotelEarning, 2);

        // 2. User & Entity Counts
        $totalUsers = User::role('USER')->count();
        $totalMerchants = User::role([
            'ECOMMERCE_MERCHANT',
            'RESTAURANT_MERCHANT',
            'HOTEL_MERCHANT',
            'BUS_MERCHANT',
        ])->count();
        $totalOrders = Order::count();
        $totalRiders = User::role('RIDER')->count();

        return $this->apiSuccess('Analytics overview metrics retrieved successfully', [
            'total_earning'           => $totalEarning,
            'currency'                => $currency,
            'formatted_total_earning' => "{$currency} " . number_format($totalEarning, 2),
            'total_users'             => $totalUsers,
            'total_merchants'         => $totalMerchants,
            'total_orders'            => $totalOrders,
            'total_riders'            => $totalRiders,
        ]);
    }

    /**
     * Endpoint 2: Section 1 - 12-Month Revenue Curve
     */
    public function revenue(Request $request): JsonResponse
    {
        $year = (int) $request->input('year', date('Y'));
        $commissionRate = (float) (PlatformSetting::where('key', 'merchant_commission_percent')->value('value') ?? 11.00);
        $currency = (string) (PlatformSetting::where('key', 'currency')->value('value') ?? 'KES');

        // 1. Orders revenue by month
        $ordersMonthly = Order::where('status', 'delivered')
            ->whereYear('created_at', $year)
            ->selectRaw('MONTH(created_at) as month, SUM(admin_commission) as earnings, SUM(total_amount + COALESCE(delivery_fee, 0)) as gross')
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        // 2. Bus bookings revenue by month
        $busMonthly = BusBooking::where('status', 'paid')
            ->whereYear('created_at', $year)
            ->selectRaw('MONTH(created_at) as month, SUM(total_price) as gross')
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        // 3. Hotel bookings revenue by month
        $hotelMonthly = HotelBooking::whereIn('status', ['paid', 'confirmed', 'checked_in', 'checked_out'])
            ->whereYear('created_at', $year)
            ->selectRaw('MONTH(created_at) as month, SUM(total_price) as gross')
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        $monthNames = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
        ];

        $monthlyData = [];
        $totalYearRevenue = 0.0;
        $totalYearGross = 0.0;

        foreach ($monthNames as $mNum => $mName) {
            $orderEarn = (float) ($ordersMonthly[$mNum]->earnings ?? 0);
            $orderGross = (float) ($ordersMonthly[$mNum]->gross ?? 0);

            $busGross = (float) ($busMonthly[$mNum]->gross ?? 0);
            $busEarn = round($busGross * ($commissionRate / 100), 2);

            $hotelGross = (float) ($hotelMonthly[$mNum]->gross ?? 0);
            $hotelEarn = round($hotelGross * ($commissionRate / 100), 2);

            $monthRevenue = round($orderEarn + $busEarn + $hotelEarn, 2);
            $monthGross = round($orderGross + $busGross + $hotelGross, 2);

            $totalYearRevenue += $monthRevenue;
            $totalYearGross += $monthGross;

            $monthlyData[] = [
                'month'             => $mName,
                'month_number'      => $mNum,
                'revenue'           => $monthRevenue,
                'gross_sales'       => $monthGross,
                'formatted_revenue' => $this->formatAmountCompact($monthRevenue),
            ];
        }

        // Available years for dropdown
        $earliest = Order::min('created_at');
        $startYear = $earliest ? (int) Carbon::parse($earliest)->format('Y') : (int) date('Y');
        $currentYear = (int) date('Y');
        $availableYears = range($currentYear, min($startYear, $currentYear - 2));

        return $this->apiSuccess('Revenue analytics retrieved successfully', [
            'year'               => $year,
            'currency'           => $currency,
            'total_year_revenue' => round($totalYearRevenue, 2),
            'total_year_gross'   => round($totalYearGross, 2),
            'available_years'    => array_values($availableYears),
            'monthly_revenue'    => $monthlyData,
        ]);
    }

    /**
     * Endpoint 3: Section 2 - Product Categories Donut/Pie Chart
     */
    public function categories(Request $request): JsonResponse
    {
        $period = strtolower(trim((string) $request->input('period', 'today')));
        $validPeriods = ['today', 'week', 'month', 'year', 'all'];
        if (!in_array($period, $validPeriods)) {
            $period = 'today';
        }

        // 1. Build query joining OrderItem -> Product -> ProductCategory
        $query = OrderItem::join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('product_categories', 'products.category_id', '=', 'product_categories.id')
            ->where('orders.status', '!=', 'cancelled');

        // 2. Apply period filter
        match ($period) {
            'today' => $query->whereDate('orders.created_at', today()),
            'week'  => $query->whereBetween('orders.created_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'month' => $query->whereBetween('orders.created_at', [now()->startOfMonth(), now()->endOfMonth()]),
            'year'  => $query->whereYear('orders.created_at', now()->year),
            'all'   => null,
        };

        // Select items with category hierarchy
        $items = $query->select([
            'order_items.quantity',
            'order_items.price_at_time_of_purchase',
            'product_categories.id as category_id',
            'product_categories.name as category_name',
            'product_categories.parent_id',
        ])->get();

        // If today has zero items, fallback gracefully to 'all' or show empty state cleanly
        $isFallback = false;
        if ($items->isEmpty() && $period === 'today') {
            $items = OrderItem::join('orders', 'order_items.order_id', '=', 'orders.id')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->join('product_categories', 'products.category_id', '=', 'product_categories.id')
                ->where('orders.status', '!=', 'cancelled')
                ->select([
                    'order_items.quantity',
                    'order_items.price_at_time_of_purchase',
                    'product_categories.id as category_id',
                    'product_categories.name as category_name',
                    'product_categories.parent_id',
                ])->get();
            $isFallback = true;
        }

        // Cache all categories to resolve root parent quickly
        $allCategories = ProductCategory::all()->keyBy('id');

        $aggregated = [];
        $totalSalesOverall = 0.0;

        foreach ($items as $item) {
            // Find root parent name
            $catId = $item->category_id;
            $rootName = $item->category_name;

            while ($catId && isset($allCategories[$catId]) && $allCategories[$catId]->parent_id) {
                $parentId = $allCategories[$catId]->parent_id;
                if (isset($allCategories[$parentId])) {
                    $rootName = $allCategories[$parentId]->name;
                    $catId = $parentId;
                } else {
                    break;
                }
            }

            $lineTotal = (float) $item->quantity * (float) $item->price_at_time_of_purchase;
            $totalSalesOverall += $lineTotal;

            if (!isset($aggregated[$rootName])) {
                $aggregated[$rootName] = [
                    'category_name' => $rootName,
                    'items_sold'    => 0,
                    'total_sales'   => 0.0,
                ];
            }

            $aggregated[$rootName]['items_sold'] += (int) $item->quantity;
            $aggregated[$rootName]['total_sales'] += $lineTotal;
        }

        // Sort descending by sales
        uasort($aggregated, fn ($a, $b) => $b['total_sales'] <=> $a['total_sales']);

        // Format result with percentages and colors
        $categoriesList = [];
        $colorIndex = 0;
        $colorCount = count(self::CATEGORY_COLORS);

        foreach ($aggregated as $cat) {
            $pct = $totalSalesOverall > 0 ? round(($cat['total_sales'] / $totalSalesOverall) * 100, 1) : 0.0;
            $categoriesList[] = [
                'category_name' => $cat['category_name'],
                'items_sold'    => $cat['items_sold'],
                'total_sales'   => round($cat['total_sales'], 2),
                'percentage'    => $pct,
                'color'         => self::CATEGORY_COLORS[$colorIndex % $colorCount],
            ];
            $colorIndex++;
        }

        $availablePeriods = [
            ['label' => 'Today', 'value' => 'today'],
            ['label' => 'This Week', 'value' => 'week'],
            ['label' => 'This Month', 'value' => 'month'],
            ['label' => 'This Year', 'value' => 'year'],
            ['label' => 'All Time', 'value' => 'all'],
        ];

        return $this->apiSuccess('Product categories analytics retrieved successfully', [
            'period'            => $period,
            'is_fallback_all'   => $isFallback,
            'total_sales'       => round($totalSalesOverall, 2),
            'available_periods' => $availablePeriods,
            'categories'        => array_values($categoriesList),
        ]);
    }

    /**
     * Endpoint 4: Section 3 - Orders Performance (Success vs Cancel)
     */
    public function ordersPerformance(Request $request): JsonResponse
    {
        $year = (int) $request->input('year', date('Y'));

        // Query orders grouped by month and completion state
        $ordersMonthly = Order::whereYear('created_at', $year)
            ->selectRaw("
                MONTH(created_at) as month,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as success_count,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancel_count
            ")
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        $monthNames = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
        ];

        $monthlyData = [];
        $totalSuccess = 0;
        $totalCancelled = 0;

        foreach ($monthNames as $mNum => $mName) {
            $success = (int) ($ordersMonthly[$mNum]->success_count ?? 0);
            $cancel = (int) ($ordersMonthly[$mNum]->cancel_count ?? 0);

            $totalSuccess += $success;
            $totalCancelled += $cancel;

            $monthlyData[] = [
                'month'         => $mName,
                'month_number'  => $mNum,
                'success_count' => $success,
                'cancel_count'  => $cancel,
            ];
        }

        // Available years for dropdown
        $earliest = Order::min('created_at');
        $startYear = $earliest ? (int) Carbon::parse($earliest)->format('Y') : (int) date('Y');
        $currentYear = (int) date('Y');
        $availableYears = range($currentYear, min($startYear, $currentYear - 2));

        return $this->apiSuccess('Orders performance retrieved successfully', [
            'year'                => $year,
            'available_years'     => array_values($availableYears),
            'summary'             => [
                'total_success'   => $totalSuccess,
                'total_cancelled' => $totalCancelled,
            ],
            'monthly_performance' => $monthlyData,
        ]);
    }

    /**
     * Helper to format numbers compactly (e.g. 13.5k).
     */
    private function formatAmountCompact(float $amount): string
    {
        if ($amount >= 1000000) {
            return round($amount / 1000000, 1) . 'M';
        }
        if ($amount >= 1000) {
            return round($amount / 1000, 1) . 'k';
        }
        return number_format($amount, 2);
    }
}
