<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PayoutRequest;
use App\Models\PlatformSetting;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    use ApiResponseTrait;

    /**
     * List transaction history (completed & canceled transactions only, excluding pending withdrawal requests).
     */
    public function index(Request $request): JsonResponse
    {
        $defaultCurrency = (string) (PlatformSetting::where('key', 'currency')->value('value') ?? 'KES');

        $query = PayoutRequest::with([
            'user.merchantProfile',
            'user.riderProfile',
            'user.roles',
            'user.wallet',
        ])
        // Strictly exclude pending withdrawal requests - only show processed transaction history
        ->whereIn('status', ['completed', 'rejected', 'failed', 'cancelled']);

        // Filter by Status (completed or canceled)
        if ($request->filled('status') && $request->status !== 'all' && $request->status !== 'history') {
            $status = strtolower(trim((string) $request->status));
            if (in_array($status, ['canceled', 'cancelled', 'rejected', 'failed'])) {
                $query->whereIn('status', ['rejected', 'failed', 'cancelled']);
            } elseif ($status === 'completed') {
                $query->where('status', 'completed');
            } else {
                // Any other status (such as 'pending') is strictly excluded from transactions
                $query->whereRaw('1 = 0');
            }
        }

        // Filter by Account Type (merchant or rider)
        $accountType = strtolower(trim((string) ($request->input('account_type', $request->input('role', 'all')))));
        if ($accountType !== 'all' && !empty($accountType)) {
            if ($accountType === 'merchant') {
                $query->whereHas('user.roles', function ($r) {
                    $r->whereIn('name', ['ECOMMERCE_MERCHANT', 'RESTAURANT_MERCHANT', 'HOTEL_MERCHANT', 'BUS_MERCHANT']);
                });
            } elseif ($accountType === 'rider') {
                $query->whereHas('user.roles', function ($r) {
                    $r->where('name', 'RIDER');
                });
            }
        }

        // Search by user name, email, mpesa_number, or transaction_reference
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('mpesa_number', 'LIKE', "%{$search}%")
                  ->orWhere('transaction_reference', 'LIKE', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'LIKE', "%{$search}%")
                         ->orWhere('email', 'LIKE', "%{$search}%");
                  });
            });
        }

        // Date range filter
        $dateFrom = $request->input('date_from', $request->input('from_date'));
        $dateTo = $request->input('date_to', $request->input('to_date'));

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $paginator = $query->latest()->paginate($perPage);

        $paginator->through(function (PayoutRequest $payout) use ($defaultCurrency) {
            $user = $payout->user;
            $roleNames = $user ? $user->getRoleNames() : collect();
            $isMerchant = $roleNames->intersect(['ECOMMERCE_MERCHANT', 'RESTAURANT_MERCHANT', 'HOTEL_MERCHANT', 'BUS_MERCHANT'])->isNotEmpty();
            $accountType = $isMerchant ? 'Merchant' : ($roleNames->contains('RIDER') ? 'Rider' : 'User');

            $statusLabel = ($payout->status === 'completed') ? 'Completed' : 'Canceled';
            $currency = $user?->wallet?->currency ?? $defaultCurrency;

            return [
                'id'             => (int) $payout->id,
                'name'           => (string) ($user?->name ?? 'N/A'),
                'email'          => (string) ($user?->email ?? ''),
                'account_type'   => $accountType,
                'mpesa_number'   => (string) $payout->mpesa_number,
                'amount'         => (float) $payout->amount,
                'currency'       => $currency,
                'transaction_id' => (string) ($payout->transaction_reference ?: ('#TXN-' . str_pad($payout->id, 5, '0', STR_PAD_LEFT))),
                'date'           => $payout->created_at ? $payout->created_at->format('d-m-Y') : null,
                'created_at'     => $payout->created_at?->toIso8601String(),
                'status'         => (string) $payout->status,
                'status_label'   => $statusLabel,
            ];
        });

        $filterOptions = [
            'account_types' => [
                ['value' => 'all', 'label' => 'All Account Types'],
                ['value' => 'merchant', 'label' => 'Merchant'],
                ['value' => 'rider', 'label' => 'Rider'],
            ],
            'statuses' => [
                ['value' => 'all', 'label' => 'All Status'],
                ['value' => 'completed', 'label' => 'Completed'],
                ['value' => 'canceled', 'label' => 'Canceled'],
            ],
        ];

        return $this->apiSuccess('Transactions history retrieved successfully', [
            'filter_options' => $filterOptions,
            'transactions'   => $paginator,
        ]);
    }

    /**
     * Get details of a single transaction record (completed/canceled only).
     */
    public function show(string $id): JsonResponse
    {
        $defaultCurrency = (string) (PlatformSetting::where('key', 'currency')->value('value') ?? 'KES');

        $payout = PayoutRequest::with([
            'user.merchantProfile',
            'user.riderProfile',
            'user.roles',
            'user.wallet',
            'processedByAdmin',
        ])
        ->whereIn('status', ['completed', 'rejected', 'failed', 'cancelled'])
        ->find($id);

        if (!$payout) {
            return $this->apiError('Transaction record not found', 404);
        }

        $user = $payout->user;
        $roleNames = $user ? $user->getRoleNames() : collect();
        $isMerchant = $roleNames->intersect(['ECOMMERCE_MERCHANT', 'RESTAURANT_MERCHANT', 'HOTEL_MERCHANT', 'BUS_MERCHANT'])->isNotEmpty();
        $accountType = $isMerchant ? 'Merchant' : ($roleNames->contains('RIDER') ? 'Rider' : 'User');
        $statusLabel = ($payout->status === 'completed') ? 'Completed' : 'Canceled';
        $currency = $user?->wallet?->currency ?? $defaultCurrency;

        return $this->apiSuccess('Transaction details retrieved successfully', [
            'transaction' => [
                'id'                    => (int) $payout->id,
                'name'                  => (string) ($user?->name ?? 'N/A'),
                'email'                 => (string) ($user?->email ?? ''),
                'account_type'          => $accountType,
                'mpesa_number'          => (string) $payout->mpesa_number,
                'amount'                => (float) $payout->amount,
                'currency'              => $currency,
                'transaction_id'        => (string) ($payout->transaction_reference ?: ('#TXN-' . str_pad($payout->id, 5, '0', STR_PAD_LEFT))),
                'date'                  => $payout->created_at ? $payout->created_at->format('d-m-Y') : null,
                'created_at'            => $payout->created_at?->toIso8601String(),
                'status'                => (string) $payout->status,
                'status_label'          => $statusLabel,
                'payment_mode'          => $payout->payment_mode,
                'admin_notes'           => $payout->admin_notes,
                'rejection_reason'      => $payout->rejection_reason,
                'processed_at'          => $payout->processed_at?->toIso8601String(),
                'processed_by'          => $payout->processedByAdmin ? [
                    'id'    => (int) $payout->processedByAdmin->id,
                    'name'  => (string) $payout->processedByAdmin->name,
                    'email' => (string) $payout->processedByAdmin->email,
                ] : null,
            ],
        ]);
    }
}
