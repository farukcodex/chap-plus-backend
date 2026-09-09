<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminUserResource;
use App\Models\User;
use App\Models\Wallet;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get Admin Dashboard KPI metrics
     */
    public function index(): JsonResponse
    {
        $metrics = $this->getMetrics();

        return $this->apiSuccess('Admin dashboard metrics retrieved successfully', $metrics);
    }

    /**
     * Get standalone User Management registration chart data
     */
    public function chart(Request $request): JsonResponse
    {
        $chart = $this->getChartData($request);

        return $this->apiSuccess('Dashboard chart data retrieved successfully', $chart);
    }

    /**
     * Get recently registered users with search and pagination support (Sanitized Resource structure)
     */
    public function recentUsers(Request $request): JsonResponse
    {
        $perPage = max(1, (int) $request->input('per_page', 10));
        $query = User::with(['roles:id,name', 'userProfile', 'merchantProfile', 'riderProfile'])
            ->latest();

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhereHas('userProfile', fn ($p) => $p->where('phone_number', 'like', "%{$search}%"))
                  ->orWhereHas('merchantProfile', fn ($p) => $p->where('phone_number', 'like', "%{$search}%"))
                  ->orWhereHas('riderProfile', fn ($p) => $p->where('phone_number', 'like', "%{$search}%"));
            });
        }

        $users = $query->paginate($perPage);
        $users->through(fn ($user) => (new AdminUserResource($user))->resolve());

        return $this->apiSuccess('Recently registered users retrieved successfully', $users);
    }

    /**
     * Compute Top KPI Metrics
     */
    private function getMetrics(): array
    {
        // 1. Total Earning from Admin Wallet
        $adminUser = User::role('ADMIN')->first();
        $adminWallet = $adminUser ? Wallet::where('user_id', $adminUser->id)->first() : null;

        $totalEarning = $adminWallet ? (float) $adminWallet->balance : 0.0;
        $currency = $adminWallet?->currency ?? 'USD';

        $currencySymbol = match($currency) {
            'USD' => '$',
            'KES' => 'KES',
            'EUR' => '€',
            'GBP' => '£',
            default => $currency,
        };
        $formattedEarning = "{$currencySymbol} " . number_format($totalEarning);

        // 2. Counts by Role
        $totalUsers = User::role('USER')->count();
        $totalMerchants = User::role([
            'ECOMMERCE_MERCHANT',
            'RESTAURANT_MERCHANT',
            'HOTEL_MERCHANT',
            'BUS_MERCHANT'
        ])->count();
        $totalRiders = User::role('RIDER')->count();

        return [
            'total_earning'           => $totalEarning,
            'currency'                => $currency,
            'formatted_total_earning' => $formattedEarning,
            'total_users'             => $totalUsers,
            'total_merchants'         => $totalMerchants,
            'total_riders'            => $totalRiders,
        ];
    }

    /**
     * Compute monthly registrations for the chart
     */
    private function getChartData(Request $request): array
    {
        $year = (int) ($request->input('year', date('Y')));
        $accountType = strtolower((string) $request->input('account_type', 'all'));

        $query = User::whereYear('created_at', $year);

        if ($accountType !== 'all') {
            $roleName = match ($accountType) {
                'user'                                   => 'USER',
                'product_merchant', 'ecommerce_merchant' => 'ECOMMERCE_MERCHANT',
                'restaurant_merchant'                    => 'RESTAURANT_MERCHANT',
                'hotel_merchant'                         => 'HOTEL_MERCHANT',
                'bus_merchant'                           => 'BUS_MERCHANT',
                'rider'                                  => 'RIDER',
                default                                  => null,
            };

            if ($roleName) {
                $query->role($roleName);
            }
        }

        $monthlyCounts = $query->selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->groupBy('month')
            ->pluck('count', 'month')
            ->toArray();

        $monthNames = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
        ];

        $monthlyData = [];
        foreach ($monthNames as $monthNum => $monthName) {
            $monthlyData[] = [
                'month'        => $monthName,
                'month_number' => $monthNum,
                'count'        => (int) ($monthlyCounts[$monthNum] ?? 0),
            ];
        }

        // Available years for dropdown
        $earliestYear = User::min('created_at');
        $startYear = $earliestYear ? (int) Carbon::parse($earliestYear)->format('Y') : (int) date('Y');
        $currentYear = (int) date('Y');
        $availableYears = range($currentYear, min($startYear, $currentYear - 2));

        $availableAccountTypes = [
            ['label' => 'All', 'value' => 'all'],
            ['label' => 'User', 'value' => 'user'],
            ['label' => 'Product Merchant', 'value' => 'product_merchant'],
            ['label' => 'Restaurant Merchant', 'value' => 'restaurant_merchant'],
            ['label' => 'Hotel Merchant', 'value' => 'hotel_merchant'],
            ['label' => 'Bus Merchant', 'value' => 'bus_merchant'],
            ['label' => 'Rider', 'value' => 'rider'],
        ];

        return [
            'year'                    => $year,
            'account_type'            => $accountType,
            'available_years'         => array_values($availableYears),
            'available_account_types' => $availableAccountTypes,
            'monthly_data'            => $monthlyData,
        ];
    }
}
