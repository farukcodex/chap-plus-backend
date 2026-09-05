<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Refund;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $refunds = Refund::with(['user', 'refundable'])->latest()->paginate(15);
        return $this->apiSuccess('Refunds retrieved', ['refunds' => $refunds]);
    }

    public function processAutomatic(Request $request, string $id): JsonResponse
    {
        $refund = Refund::with('refundable')->find($id);

        if (!$refund) {
            return $this->apiError('Refund record not found', 404);
        }

        if ($refund->status !== 'pending') {
            return $this->apiError('Only pending refunds can be processed', 400);
        }

        // TODO: Integrate actual M-Pesa B2C API here using $refund->refundable->customer_phone_number
        // For now, we will mock the B2C trigger as successful
        $mockB2CConversationId = 'B2C_' . uniqid();

        $refund->update([
            'status' => 'processing',
            'mpesa_conversation_id' => $mockB2CConversationId,
            'processed_by_admin_id' => $request->user()->id,
            'admin_notes' => 'Triggered M-Pesa B2C API automatically.'
        ]);

        return $this->apiSuccess('M-Pesa B2C Refund triggered successfully', ['refund' => $refund]);
    }

    public function processManual(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000'
        ]);

        $refund = Refund::find($id);

        if (!$refund) {
            return $this->apiError('Refund record not found', 404);
        }

        if ($refund->status !== 'pending') {
            return $this->apiError('Only pending refunds can be processed', 400);
        }

        // The Admin physically sent the cash and is marking it complete in the system
        $refund->update([
            'status' => 'completed',
            'processed_by_admin_id' => $request->user()->id,
            'admin_notes' => $validated['notes'] ?? 'Manually refunded by Admin'
        ]);

        return $this->apiSuccess('Refund marked as completed manually', ['refund' => $refund]);
    }
}
