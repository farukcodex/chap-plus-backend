<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminPayoutResource;
use App\Models\PayoutRequest;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Notifications\PayoutStatusUpdatedNotification;
use App\Services\MpesaService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayoutController extends Controller
{
    use ApiResponseTrait;

    protected MpesaService $mpesaService;

    public function __construct(MpesaService $mpesaService)
    {
        $this->mpesaService = $mpesaService;
    }

    /**
     * List all payout requests with filters and summary statistics.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PayoutRequest::with([
            'user.merchantProfile',
            'user.riderProfile',
            'user.roles',
            'user.wallet',
            'processedByAdmin'
        ]);

        // Filter by Status (supports 'history' for Tab 1, and 'canceled' alias)
        if ($request->filled('status') && $request->status !== 'all') {
            $status = strtolower(trim((string) $request->status));
            if ($status === 'history') {
                $query->whereIn('status', ['completed', 'rejected', 'failed']);
            } elseif (in_array($status, ['canceled', 'cancelled', 'rejected'])) {
                $query->whereIn('status', ['rejected', 'cancelled', 'failed']);
            } else {
                $query->where('status', $status);
            }
        }

        // Filter by Requester Role / Account Type
        $roleFilter = strtolower(trim((string) ($request->input('account_type', $request->input('role', 'all')))));
        if ($roleFilter !== 'all' && !empty($roleFilter)) {
            if ($roleFilter === 'merchant') {
                $query->whereHas('user.roles', function ($r) {
                    $r->whereIn('name', ['ECOMMERCE_MERCHANT', 'RESTAURANT_MERCHANT', 'HOTEL_MERCHANT', 'BUS_MERCHANT']);
                });
            } elseif ($roleFilter === 'rider') {
                $query->whereHas('user.roles', function ($r) {
                    $r->where('name', 'RIDER');
                });
            }
        }

        // Search by user name, email, mpesa_number, or transaction_reference
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('mpesa_number', 'LIKE', "%{$search}%")
                  ->orWhere('transaction_reference', 'LIKE', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'LIKE', "%{$search}%")
                         ->orWhere('email', 'LIKE', "%{$search}%");
                  });
            });
        }

        // Date range filter (supports both date_from/date_to and from_date/to_date)
        $dateFrom = $request->input('date_from', $request->input('from_date'));
        $dateTo = $request->input('date_to', $request->input('to_date'));

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        // Calculate summary statistics
        $summary = [
            'total_pending_count'     => (int) PayoutRequest::where('status', 'pending')->count(),
            'total_pending_amount'    => (float) PayoutRequest::where('status', 'pending')->sum('amount'),
            'total_completed_amount'  => (float) PayoutRequest::where('status', 'completed')->sum('amount'),
            'total_rejected_count'    => (int) PayoutRequest::where('status', 'rejected')->count(),
        ];

        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $payouts = $query->latest()->paginate($perPage);
        $payouts->through(fn($payout) => (new AdminPayoutResource($payout))->toArray($request));

        return $this->apiSuccess('Payout requests retrieved successfully', [
            'summary' => $summary,
            'payouts' => $payouts,
        ]);
    }

    /**
     * Get details of a single payout request.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $payout = PayoutRequest::with([
            'user.merchantProfile',
            'user.riderProfile',
            'user.roles',
            'user.wallet',
            'processedByAdmin'
        ])->find($id);

        if (!$payout) {
            return $this->apiError('Payout request not found', 404);
        }

        return $this->apiSuccess('Payout request details retrieved', [
            'payout' => new AdminPayoutResource($payout)
        ]);
    }

    /**
     * Manual Mark as Paid: Admin completed the transfer externally and marks it paid.
     */
    public function approveManual(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'transaction_reference' => 'nullable|string|max:100',
            'admin_notes'           => 'nullable|string|max:1000',
            'notes'                 => 'nullable|string|max:1000', // alias
        ]);

        $payout = PayoutRequest::with(['user', 'processedByAdmin'])->find($id);

        if (!$payout) {
            return $this->apiError('Payout request not found', 404);
        }

        if ($payout->status !== 'pending') {
            return $this->apiError("Only pending payout requests can be approved. Current status: {$payout->status}", 400);
        }

        $reference = $validated['transaction_reference'] ?? ('MANUAL_' . strtoupper(uniqid()));
        $notes = $validated['admin_notes'] ?? $validated['notes'] ?? 'Manually marked as paid by Admin';

        $payout->update([
            'status'                => 'completed',
            'payment_mode'          => 'manual',
            'transaction_reference' => $reference,
            'admin_notes'           => $notes,
            'processed_by_admin_id' => $request->user()->id,
            'processed_at'          => now(),
        ]);

        $payout->load(['user.merchantProfile', 'user.riderProfile', 'user.roles', 'user.wallet', 'processedByAdmin']);

        // Send notification to user
        if ($payout->user) {
            $payout->user->notify(new PayoutStatusUpdatedNotification($payout));
        }

        return $this->apiSuccess('Payout request marked as paid manually', [
            'payout' => new AdminPayoutResource($payout)
        ]);
    }

    /**
     * Automated Option: Trigger Safaricom M-Pesa B2C payout automatically.
     */
    public function approveAutomatic(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'remarks' => 'nullable|string|max:100',
        ]);

        $payout = PayoutRequest::with(['user', 'processedByAdmin'])->find($id);

        if (!$payout) {
            return $this->apiError('Payout request not found', 404);
        }

        if ($payout->status !== 'pending') {
            return $this->apiError("Only pending payout requests can be processed. Current status: {$payout->status}", 400);
        }

        try {
            $remarks = $validated['remarks'] ?? 'ChapPlus Payout';
            $b2cResponse = $this->mpesaService->initiateB2cPayment(
                $payout->mpesa_number,
                $payout->amount,
                $remarks
            );

            // If immediate transaction ID returned (simulation or direct B2C)
            $isDirectSuccess = isset($b2cResponse['TransactionID']);
            $status = $isDirectSuccess ? 'completed' : 'processing';

            $payout->update([
                'status'                           => $status,
                'payment_mode'                     => 'automated',
                'transaction_reference'            => $b2cResponse['TransactionID'] ?? null,
                'mpesa_conversation_id'            => $b2cResponse['ConversationID'] ?? null,
                'mpesa_originator_conversation_id' => $b2cResponse['OriginatorConversationID'] ?? null,
                'admin_notes'                      => 'Automated M-Pesa B2C payment triggered.',
                'processed_by_admin_id'            => $request->user()->id,
                'processed_at'                     => now(),
            ]);

            $payout->load(['user.merchantProfile', 'user.riderProfile', 'user.roles', 'user.wallet', 'processedByAdmin']);

            if ($payout->user) {
                $payout->user->notify(new PayoutStatusUpdatedNotification($payout));
            }

            return $this->apiSuccess('Automated M-Pesa B2C payout processed successfully', [
                'payout'         => new AdminPayoutResource($payout),
                'mpesa_response' => $b2cResponse,
            ]);

        } catch (\Exception $e) {
            Log::error("Payout B2C Error for ID {$id}: " . $e->getMessage());
            return $this->apiError('Failed to process automated payout: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Reject Payout Request: Reject with reason and refund wallet balance immediately.
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $payout = PayoutRequest::with(['user', 'processedByAdmin'])->find($id);

        if (!$payout) {
            return $this->apiError('Payout request not found', 404);
        }

        if ($payout->status !== 'pending') {
            return $this->apiError("Only pending payout requests can be rejected. Current status: {$payout->status}", 400);
        }

        DB::transaction(function () use ($payout, $validated, $request) {
            $payout->update([
                'status'                => 'rejected',
                'rejection_reason'      => $validated['reason'],
                'processed_by_admin_id' => $request->user()->id,
                'processed_at'          => now(),
            ]);

            // Restore user's wallet balance
            $wallet = Wallet::firstOrCreate(['user_id' => $payout->user_id]);
            $wallet->increment('balance', $payout->amount);

            // Log refund credit transaction
            WalletTransaction::create([
                'wallet_id'      => $wallet->id,
                'type'           => 'credit',
                'amount'         => $payout->amount,
                'reference_type' => PayoutRequest::class,
                'reference_id'   => $payout->id,
                'description'    => 'Payout Request Rejected - Funds Refunded: ' . $validated['reason'],
            ]);
        });

        $payout->load(['user.merchantProfile', 'user.riderProfile', 'user.roles', 'user.wallet', 'processedByAdmin']);

        if ($payout->user) {
            $payout->user->notify(new PayoutStatusUpdatedNotification($payout));
        }

        return $this->apiSuccess('Payout request rejected and funds refunded to user wallet', [
            'payout' => new AdminPayoutResource($payout)
        ]);
    }
}
