<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get current commission percentages for all services and entities.
     */
    public function index(): JsonResponse
    {
        $commissions = PlatformSetting::getCommissionRates();

        return $this->apiSuccess('Commissions retrieved successfully', $commissions);
    }

    /**
     * Update commission percentages.
     * Supports flat keys (e.g. ecommerce_commission_percent or ecommerce)
     * as well as nested {"commissions": {...}}.
     */
    public function update(Request $request): JsonResponse
    {
        $input = $request->input('commissions', $request->all());

        // Map shorthand keys (ecommerce => ecommerce_commission_percent)
        $mapped = [];
        $keyMap = [
            'ecommerce'  => 'ecommerce_commission_percent',
            'restaurant' => 'restaurant_commission_percent',
            'bus'        => 'bus_commission_percent',
            'hotel'      => 'hotel_commission_percent',
            'rider'      => 'rider_commission_percent',
            'merchant'   => 'merchant_commission_percent',
        ];

        foreach ($input as $key => $val) {
            $targetKey = $keyMap[$key] ?? $key;
            $mapped[$targetKey] = $val;
        }

        $validated = validator($mapped, [
            'ecommerce_commission_percent'  => 'nullable|numeric|min:0|max:100',
            'restaurant_commission_percent' => 'nullable|numeric|min:0|max:100',
            'bus_commission_percent'        => 'nullable|numeric|min:0|max:100',
            'hotel_commission_percent'      => 'nullable|numeric|min:0|max:100',
            'rider_commission_percent'      => 'nullable|numeric|min:0|max:100',
            'merchant_commission_percent'   => 'nullable|numeric|min:0|max:100',
        ])->validate();

        foreach ($validated as $key => $value) {
            if ($value !== null) {
                PlatformSetting::updateOrCreate(
                    ['key' => $key],
                    ['value' => number_format((float) $value, 2, '.', '')]
                );
            }
        }

        $updated = PlatformSetting::getCommissionRates();

        return $this->apiSuccess('Commissions updated successfully', $updated);
    }
}