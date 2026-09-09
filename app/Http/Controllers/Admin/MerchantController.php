<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class MerchantController extends Controller
{
    use ApiResponseTrait;

    protected const MERCHANT_ROLES = [
        'ECOMMERCE_MERCHANT',
        'RESTAURANT_MERCHANT',
        'HOTEL_MERCHANT',
        'BUS_MERCHANT',
    ];

    /**
     * List all merchants with optional search, status, and role filters.
     */
    public function index(Request $request): JsonResponse
    {
        $role = $request->query('role') ?? $request->query('merchant_type');

        if ($role && in_array($role, self::MERCHANT_ROLES)) {
            $query = User::role($role)->with(['merchantProfile', 'roles:id,name']);
        } else {
            $query = User::role(self::MERCHANT_ROLES)->with(['merchantProfile', 'roles:id,name']);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhereHas('merchantProfile', function ($mpq) use ($search) {
                      $mpq->where('business_name', 'LIKE', "%{$search}%")
                          ->orWhere('phone_number', 'LIKE', "%{$search}%");
                  });
            });
        }

        if ($request->filled('status')) {
            $status = $request->status;
            $query->whereHas('merchantProfile', function ($mpq) use ($status) {
                $mpq->where('status', $status);
            });
        }

        $merchants = $query->latest()->paginate($request->integer('per_page', 20));

        return $this->apiSuccess('Merchants retrieved successfully', ['merchants' => $merchants]);
    }

    /**
     * Get single merchant details.
     */
    public function show(string $id): JsonResponse
    {
        $merchant = User::role(self::MERCHANT_ROLES)
            ->with(['merchantProfile', 'roles:id,name'])
            ->find($id);

        if (!$merchant) {
            return $this->apiError('Merchant not found', 404);
        }

        return $this->apiSuccess('Merchant details retrieved', ['merchant' => $merchant]);
    }

    /**
     * Update merchant verification status.
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:approved,rejected,pending'
        ]);

        $merchant = User::role(self::MERCHANT_ROLES)
            ->with('merchantProfile')
            ->find($id);

        if (!$merchant || !$merchant->merchantProfile) {
            return $this->apiError('Merchant profile not found', 404);
        }

        $merchant->merchantProfile->update([
            'status' => $validated['status']
        ]);

        $merchant->load(['merchantProfile', 'roles:id,name']);

        return $this->apiSuccess('Merchant status updated successfully', ['merchant' => $merchant]);
    }
}
