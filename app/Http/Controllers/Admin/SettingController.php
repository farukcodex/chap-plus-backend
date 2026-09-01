<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    use ApiResponseTrait;

    public function index(): JsonResponse
    {
        $settings = PlatformSetting::all()->pluck('value', 'key');
        return $this->apiSuccess('Settings retrieved successfully', $settings);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.merchant_commission_percent' => 'nullable|numeric|min:0|max:100',
            'settings.rider_commission_percent' => 'nullable|numeric|min:0|max:100',
            'settings.min_payout_amount' => 'nullable|numeric|min:0',
            'settings.currency' => 'nullable|string|size:3',
        ]);

        foreach ($request->settings as $key => $value) {
            PlatformSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        $settings = PlatformSetting::all()->pluck('value', 'key');

        return $this->apiSuccess('Settings updated successfully', $settings);
    }
}
