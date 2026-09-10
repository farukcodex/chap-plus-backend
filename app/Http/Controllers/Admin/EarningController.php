<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusBooking;
use App\Models\HotelBooking;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EarningController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get platform earnings overview, summary metrics, and paginated ledger records.
     */
    public function index(Request $request): JsonResponse
    {
        $commissionRate = (float) (PlatformSetting::where('key', 'merchant_commission_percent')->value('value') ?? 11.00);
        $currency = (string) (PlatformSetting::where('key', 'currency')->value('value') ?? 'KES');

        // 1. Calculate top summary metric cards
        $metrics = $this->calculateMetrics($commissionRate, $currency);

        // 2. Fetch unified earnings collection with filters
        $items = $this->fetchEarningsRecords($request, $commissionRate, $currency);

        // 3. Paginate the unified collection
        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $total = $items->count();
        $pagedItems = $items->slice(($page - 1) * $perPage, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $pagedItems,
            $total,
            $perPage,
            $page,
            [
                'path'  => $request->url(),
                'query' => $request->query(),
            ]
        );

        return $this->apiSuccess('Earnings overview retrieved successfully', [
            'metrics'  => $metrics,
            'earnings' => $paginator,
        ]);
    }

    /**
     * Export earnings ledger as a downloadable CSV file.
     */
    public function export(Request $request): StreamedResponse
    {
        $commissionRate = (float) (PlatformSetting::where('key', 'merchant_commission_percent')->value('value') ?? 11.00);
        $currency = (string) (PlatformSetting::where('key', 'currency')->value('value') ?? 'KES');

        $records = $this->fetchEarningsRecords($request, $commissionRate, $currency);
        $filename = 'platform_earnings_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($records) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Header row
            fputcsv($handle, [
                'Order / Transaction Number',
                'Merchant Name',
                'Business Name',
                'Merchant Type',
                'Item / Service',
                'Order Price',
                'Currency',
                'Commission Rate',
                'Platform Earning',
                'Merchant Earning',
                'Date',
                'Status',
            ]);

            foreach ($records as $item) {
                fputcsv($handle, [
                    $item['order_number'],
                    $item['merchant']['name'] ?? 'N/A',
                    $item['merchant']['business_name'] ?? 'N/A',
                    $item['merchant_type'],
                    $item['order_title'],
                    number_format($item['order_price'], 2, '.', ''),
                    $item['currency'],
                    $item['commission_percent'] . '%',
                    number_format($item['platform_earning'], 2, '.', ''),
                    number_format($item['merchant_earning'], 2, '.', ''),
                    $item['date'],
                    $item['status'],
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Calculate summary revenue metrics for the dashboard cards.
     */
    protected function calculateMetrics(float $commissionRate, string $currency): array
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();
        $startOfDay = now()->startOfDay();
        $endOfDay = now()->endOfDay();

        // Orders Gross (Ecommerce + Restaurant)
        $totalOrdersGross = (float) Order::where('status', 'delivered')
            ->selectRaw('COALESCE(SUM(total_amount + COALESCE(delivery_fee, 0)), 0) as aggregate')
            ->value('aggregate');

        $monthOrdersGross = (float) Order::where('status', 'delivered')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->selectRaw('COALESCE(SUM(total_amount + COALESCE(delivery_fee, 0)), 0) as aggregate')
            ->value('aggregate');

        $todayOrdersGross = (float) Order::where('status', 'delivered')
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->selectRaw('COALESCE(SUM(total_amount + COALESCE(delivery_fee, 0)), 0) as aggregate')
            ->value('aggregate');

        // Bus Gross
        $totalBusGross = (float) BusBooking::where('status', 'paid')->sum('total_price');
        $monthBusGross = (float) BusBooking::where('status', 'paid')->whereBetween('created_at', [$startOfMonth, $endOfMonth])->sum('total_price');
        $todayBusGross = (float) BusBooking::where('status', 'paid')->whereBetween('created_at', [$startOfDay, $endOfDay])->sum('total_price');

        // Hotel Gross
        $hotelStatuses = ['paid', 'confirmed', 'checked_in', 'checked_out'];
        $totalHotelGross = (float) HotelBooking::whereIn('status', $hotelStatuses)->sum('total_price');
        $monthHotelGross = (float) HotelBooking::whereIn('status', $hotelStatuses)->whereBetween('created_at', [$startOfMonth, $endOfMonth])->sum('total_price');
        $todayHotelGross = (float) HotelBooking::whereIn('status', $hotelStatuses)->whereBetween('created_at', [$startOfDay, $endOfDay])->sum('total_price');

        // Totals by service rate
        $ecomRate = PlatformSetting::getCommissionRate('ecommerce') / 100;
        $restaurantRate = PlatformSetting::getCommissionRate('restaurant') / 100;
        $busRate = PlatformSetting::getCommissionRate('bus') / 100;
        $hotelRate = PlatformSetting::getCommissionRate('hotel') / 100;

        $totalEcomGross = (float) Order::ecommerce()->where('status', 'delivered')->selectRaw('COALESCE(SUM(total_amount + COALESCE(delivery_fee, 0)), 0) as aggregate')->value('aggregate');
        $monthEcomGross = (float) Order::ecommerce()->where('status', 'delivered')->whereBetween('created_at', [$startOfMonth, $endOfMonth])->selectRaw('COALESCE(SUM(total_amount + COALESCE(delivery_fee, 0)), 0) as aggregate')->value('aggregate');
        $todayEcomGross = (float) Order::ecommerce()->where('status', 'delivered')->whereBetween('created_at', [$startOfDay, $endOfDay])->selectRaw('COALESCE(SUM(total_amount + COALESCE(delivery_fee, 0)), 0) as aggregate')->value('aggregate');

        $totalRestGross = (float) Order::restaurant()->where('status', 'delivered')->selectRaw('COALESCE(SUM(total_amount + COALESCE(delivery_fee, 0)), 0) as aggregate')->value('aggregate');
        $monthRestGross = (float) Order::restaurant()->where('status', 'delivered')->whereBetween('created_at', [$startOfMonth, $endOfMonth])->selectRaw('COALESCE(SUM(total_amount + COALESCE(delivery_fee, 0)), 0) as aggregate')->value('aggregate');
        $todayRestGross = (float) Order::restaurant()->where('status', 'delivered')->whereBetween('created_at', [$startOfDay, $endOfDay])->selectRaw('COALESCE(SUM(total_amount + COALESCE(delivery_fee, 0)), 0) as aggregate')->value('aggregate');

        $totalEarnings = round(($totalEcomGross * $ecomRate) + ($totalRestGross * $restaurantRate) + ($totalBusGross * $busRate) + ($totalHotelGross * $hotelRate), 2);
        $thisMonthEarnings = round(($monthEcomGross * $ecomRate) + ($monthRestGross * $restaurantRate) + ($monthBusGross * $busRate) + ($monthHotelGross * $hotelRate), 2);
        $todayEarnings = round(($todayEcomGross * $ecomRate) + ($todayRestGross * $restaurantRate) + ($todayBusGross * $busRate) + ($todayHotelGross * $hotelRate), 2);

        return [
            'currency'            => $currency,
            'commission_rate'     => $commissionRate,
            'commission_rates'    => PlatformSetting::getCommissionRates(),
            'total_earnings'      => $totalEarnings,
            'this_month_earnings' => $thisMonthEarnings,
            'today_earnings'      => $todayEarnings,
        ];
    }

    /**
     * Query and unify records from Orders (Ecommerce/Restaurant), Bus, and Hotel.
     */
    protected function fetchEarningsRecords(Request $request, float $commissionRate, string $defaultCurrency)
    {
        $search = $request->filled('search') ? trim($request->input('search')) : null;
        $merchantType = strtolower(trim((string) $request->input('merchant_type', 'all')));
        $statusFilter = strtolower(trim((string) $request->input('status', 'all')));
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $collection = collect();

        // 1. Ecommerce Orders
        if (in_array($merchantType, ['all', 'product', 'ecommerce'])) {
            $ecomRate = PlatformSetting::getCommissionRate('ecommerce');
            $orderQuery = Order::ecommerce()->with([
                'merchantProfile.user:id,name,email',
                'items.product:id,name',
            ]);

            $this->applyOrderFilters($orderQuery, $search, $statusFilter, $dateFrom, $dateTo);

            $ecommerceRecords = $orderQuery->get()->map(function (Order $order) use ($ecomRate, $defaultCurrency) {
                return $this->formatOrderRecord($order, 'Product Merchant', $ecomRate, $defaultCurrency);
            });

            $collection = $collection->concat($ecommerceRecords);
        }

        // 2. Restaurant Orders
        if (in_array($merchantType, ['all', 'restaurant', 'food'])) {
            $restaurantRate = PlatformSetting::getCommissionRate('restaurant');
            $restaurantQuery = Order::restaurant()->with([
                'merchantProfile.user:id,name,email',
                'items.product:id,name',
            ]);

            $this->applyOrderFilters($restaurantQuery, $search, $statusFilter, $dateFrom, $dateTo);

            $restaurantRecords = $restaurantQuery->get()->map(function (Order $order) use ($restaurantRate, $defaultCurrency) {
                return $this->formatOrderRecord($order, 'Restaurant Merchant', $restaurantRate, $defaultCurrency);
            });

            $collection = $collection->concat($restaurantRecords);
        }

        // 3. Bus Bookings
        if (in_array($merchantType, ['all', 'bus'])) {
            $busQuery = BusBooking::with([
                'merchantProfile.user:id,name,email',
                'bus:id,name,departure_place,destination_place',
            ]);

            if ($search) {
                $busQuery->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                        ->orWhereHas('merchantProfile', fn ($mq) => $mq->where('business_name', 'like', "%{$search}%"))
                        ->orWhereHas('merchantProfile.user', fn ($uq) => $uq->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('bus', fn ($bq) => $bq->where('name', 'like', "%{$search}%")
                            ->orWhere('departure_place', 'like', "%{$search}%")
                            ->orWhere('destination_place', 'like', "%{$search}%"));
                });
            }

            if ($statusFilter === 'completed') {
                $busQuery->where('status', 'paid');
            } elseif (in_array($statusFilter, ['canceled', 'cancelled'])) {
                $busQuery->where('status', 'cancelled');
            }

            if ($dateFrom) {
                $busQuery->whereDate('created_at', '>=', $dateFrom);
            }
            if ($dateTo) {
                $busQuery->whereDate('created_at', '<=', $dateTo);
            }

            $busRate = PlatformSetting::getCommissionRate('bus');
            $busRecords = $busQuery->get()->map(function (BusBooking $booking) use ($busRate, $defaultCurrency) {
                $price = (float) $booking->total_price;
                $isCompleted = ($booking->status === 'paid');
                $platformEarning = $isCompleted ? round($price * ($busRate / 100), 2) : 0.00;
                $route = $booking->bus ? "{$booking->bus->departure_place} to {$booking->bus->destination_place}" : 'Bus Ticket';
                $orderNumber = '#BUS-' . str_pad($booking->id, 5, '0', STR_PAD_LEFT);

                return [
                    'id'                 => $booking->id,
                    'order_number'       => $orderNumber,
                    'order_title'        => $route,
                    'source_type'        => 'bus',
                    'merchant_type'      => 'Bus Merchant',
                    'merchant'           => [
                        'id'            => $booking->merchantProfile?->id,
                        'name'          => $booking->merchantProfile?->user?->name ?? 'N/A',
                        'business_name' => $booking->merchantProfile?->business_name ?? 'N/A',
                        'email'         => $booking->merchantProfile?->user?->email,
                    ],
                    'order_price'        => $price,
                    'commission_percent' => $busRate,
                    'platform_earning'   => $platformEarning,
                    'merchant_earning'   => round($price - $platformEarning, 2),
                    'currency'           => $booking->merchantProfile?->currency ?? $defaultCurrency,
                    'date'               => $booking->created_at?->format('Y-m-d H:i:s'),
                    'created_at'         => $booking->created_at,
                    'status'             => $isCompleted ? 'Completed' : ($booking->status === 'cancelled' ? 'Canceled' : ucfirst($booking->status)),
                ];
            });

            $collection = $collection->concat($busRecords);
        }

        // 4. Hotel Bookings
        if (in_array($merchantType, ['all', 'hotel'])) {
            $hotelQuery = HotelBooking::with([
                'merchantProfile.user:id,name,email',
                'hotel:id,name',
            ]);

            if ($search) {
                $hotelQuery->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                        ->orWhereHas('merchantProfile', fn ($mq) => $mq->where('business_name', 'like', "%{$search}%"))
                        ->orWhereHas('merchantProfile.user', fn ($uq) => $uq->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('hotel', fn ($hq) => $hq->where('name', 'like', "%{$search}%"));
                });
            }

            $hotelCompletedStatuses = ['paid', 'confirmed', 'checked_in', 'checked_out'];
            if ($statusFilter === 'completed') {
                $hotelQuery->whereIn('status', $hotelCompletedStatuses);
            } elseif (in_array($statusFilter, ['canceled', 'cancelled'])) {
                $hotelQuery->where('status', 'cancelled');
            }

            if ($dateFrom) {
                $hotelQuery->whereDate('created_at', '>=', $dateFrom);
            }
            if ($dateTo) {
                $hotelQuery->whereDate('created_at', '<=', $dateTo);
            }

            $hotelRate = PlatformSetting::getCommissionRate('hotel');
            $hotelRecords = $hotelQuery->get()->map(function (HotelBooking $booking) use ($hotelRate, $defaultCurrency, $hotelCompletedStatuses) {
                $price = (float) $booking->total_price;
                $isCompleted = in_array($booking->status, $hotelCompletedStatuses);
                $platformEarning = $isCompleted ? round($price * ($hotelRate / 100), 2) : 0.00;
                $hotelName = $booking->hotel?->name ?? 'Hotel Reservation';
                $orderNumber = '#HTL-' . str_pad($booking->id, 5, '0', STR_PAD_LEFT);

                return [
                    'id'                 => $booking->id,
                    'order_number'       => $orderNumber,
                    'order_title'        => $hotelName,
                    'source_type'        => 'hotel',
                    'merchant_type'      => 'Hotel Merchant',
                    'merchant'           => [
                        'id'            => $booking->merchantProfile?->id,
                        'name'          => $booking->merchantProfile?->user?->name ?? 'N/A',
                        'business_name' => $booking->merchantProfile?->business_name ?? 'N/A',
                        'email'         => $booking->merchantProfile?->user?->email,
                    ],
                    'order_price'        => $price,
                    'commission_percent' => $hotelRate,
                    'platform_earning'   => $platformEarning,
                    'merchant_earning'   => round($price - $platformEarning, 2),
                    'currency'           => $booking->merchantProfile?->currency ?? $defaultCurrency,
                    'date'               => $booking->created_at?->format('Y-m-d H:i:s'),
                    'created_at'         => $booking->created_at,
                    'status'             => $isCompleted ? 'Completed' : ($booking->status === 'cancelled' ? 'Canceled' : ucfirst($booking->status)),
                ];
            });

            $collection = $collection->concat($hotelRecords);
        }

        // Sort collection descending by creation date
        return $collection->sortByDesc('created_at')->values();
    }

    /**
     * Apply common filters for Orders (ecommerce & restaurant).
     */
    protected function applyOrderFilters($query, ?string $search, string $statusFilter, ?string $dateFrom, ?string $dateTo): void
    {
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('merchantProfile', fn ($mq) => $mq->where('business_name', 'like', "%{$search}%"))
                    ->orWhereHas('merchantProfile.user', fn ($uq) => $uq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('items.product', fn ($pq) => $pq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($statusFilter === 'completed') {
            $query->where('status', 'delivered');
        } elseif (in_array($statusFilter, ['canceled', 'cancelled'])) {
            $query->where('status', 'cancelled');
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }
    }

    /**
     * Format an Order instance into the unified ledger schema.
     */
    protected function formatOrderRecord(Order $order, string $merchantType, float $commissionRate, string $defaultCurrency): array
    {
        $price = (float) $order->total_amount + (float) ($order->delivery_fee ?? 0);
        $isCompleted = ($order->status === 'delivered');

        // Use frozen snapshot values if settled, otherwise compute dynamically
        if ($isCompleted && $order->commission_settled_at !== null) {
            $platformEarning = (float) $order->admin_commission;
            $merchantEarning = (float) $order->merchant_earnings;
            $effectiveCommission = (float) ($order->merchant_commission_rate ?? $commissionRate);
        } else {
            $platformEarning = $isCompleted ? round($price * ($commissionRate / 100), 2) : 0.00;
            $merchantEarning = $isCompleted ? round($price - $platformEarning, 2) : 0.00;
            $effectiveCommission = $commissionRate;
        }

        $itemsSummary = $order->items->map(function ($it) {
            $name = $it->product?->name ?? 'Item';
            return $it->quantity > 1 ? "{$name} x {$it->quantity}" : $name;
        })->filter()->implode(', ');

        $orderNumber = $order->order_number ?? ('#ORD-' . str_pad($order->id, 5, '0', STR_PAD_LEFT));
        $orderTitle = $itemsSummary ?: "Order #{$order->id}";

        return [
            'id'                 => $order->id,
            'order_number'       => $orderNumber,
            'order_title'        => $orderTitle,
            'source_type'        => $order->type,
            'merchant_type'      => $merchantType,
            'merchant'           => [
                'id'            => $order->merchantProfile?->id,
                'name'          => $order->merchantProfile?->user?->name ?? 'N/A',
                'business_name' => $order->merchantProfile?->business_name ?? 'N/A',
                'email'         => $order->merchantProfile?->user?->email,
            ],
            'order_price'        => $price,
            'commission_percent' => $effectiveCommission,
            'platform_earning'   => $platformEarning,
            'merchant_earning'   => $merchantEarning,
            'currency'           => $order->merchantProfile?->currency ?? $defaultCurrency,
            'date'               => $order->created_at?->format('Y-m-d H:i:s'),
            'created_at'         => $order->created_at,
            'status'             => $isCompleted ? 'Completed' : ($order->status === 'cancelled' ? 'Canceled' : ucfirst(str_replace('_', ' ', $order->status))),
        ];
    }
}
