<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use League\ISO3166\ISO3166;

class PayoutSettingController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get platform minimum payout threshold settings and per-currency thresholds.
     */
    public function index(): JsonResponse
    {
        $currency = strtoupper((string) (PlatformSetting::where('key', 'currency')->value('value') ?? 'KES'));
        $minPayout = (float) (PlatformSetting::where('key', 'min_payout_amount')->value('value') ?? 500.0);
        $map = PlatformSetting::getMinPayoutByCurrency();

        $iso = new ISO3166();
        $all = $iso->all();
        $allCurrencies = [];
        foreach ($all as $c) {
            if (!empty($c['currency'])) {
                foreach ($c['currency'] as $curr) {
                    $allCurrencies[$curr] = true;
                }
            }
        }
        ksort($allCurrencies);
        $availableCurrencies = array_keys($allCurrencies);

        return $this->apiSuccess('Payout settings retrieved successfully', [
            'currency'               => $currency,
            'min_payout_amount'      => $minPayout,
            'min_payout_by_currency' => $map,
            'available_currencies'   => $availableCurrencies,
        ]);
    }

    /**
     * Update payout threshold settings.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = validator($request->all(), [
            'currency'               => 'nullable|string|size:3',
            'min_payout_amount'      => 'nullable|numeric|min:0',
            'min_payout_by_currency' => 'nullable|array',
            'min_payout_by_currency.*' => 'numeric|min:0',
        ])->validate();

        if (!empty($validated['currency'])) {
            PlatformSetting::updateOrCreate(
                ['key' => 'currency'],
                ['value' => strtoupper(trim($validated['currency']))]
            );
        }

        $defaultCurrency = strtoupper((string) (PlatformSetting::where('key', 'currency')->value('value') ?? 'KES'));

        if (isset($validated['min_payout_amount'])) {
            $minAmount = (float) $validated['min_payout_amount'];
            PlatformSetting::updateOrCreate(
                ['key' => 'min_payout_amount'],
                ['value' => (string) $minAmount]
            );

            // Sync default currency in map
            $currentMap = PlatformSetting::getMinPayoutByCurrency();
            $currentMap[$defaultCurrency] = $minAmount;
            PlatformSetting::updateOrCreate(
                ['key' => 'min_payout_by_currency'],
                ['value' => json_encode($currentMap)]
            );
        }

        if (isset($validated['min_payout_by_currency']) && is_array($validated['min_payout_by_currency'])) {
            $currentMap = PlatformSetting::getMinPayoutByCurrency();
            foreach ($validated['min_payout_by_currency'] as $curr => $val) {
                $currentMap[strtoupper(trim((string) $curr))] = (float) $val;
            }
            PlatformSetting::updateOrCreate(
                ['key' => 'min_payout_by_currency'],
                ['value' => json_encode($currentMap)]
            );
        }

        return $this->index();
    }

    /**
     * Auto-sync all currency minimum payout thresholds based on a single base KES amount using live forex rates.
     */
    public function autoSync(Request $request, \App\Services\ExchangeRateService $fxService): JsonResponse
    {
        $validated = validator($request->all(), [
            'kes_amount' => 'required|numeric|min:1',
            'rounding'   => 'nullable|boolean',
        ])->validate();

        $kesAmount = (float) $validated['kes_amount'];
        $smartRound = $request->boolean('rounding', true);

        // 1. Update primary min_payout_amount setting
        PlatformSetting::updateOrCreate(
            ['key' => 'min_payout_amount'],
            ['value' => (string) $kesAmount]
        );

        // 2. Fetch all unique global currencies
        $iso = new ISO3166();
        $allCountries = $iso->all();
        $currencyMap = [];
        foreach ($allCountries as $c) {
            if (!empty($c['currency'])) {
                foreach ($c['currency'] as $curr) {
                    $currencyMap[strtoupper(trim((string) $curr))] = true;
                }
            }
        }
        ksort($currencyMap);

        $currentMap = ['KES' => $kesAmount];
        foreach (array_keys($currencyMap) as $currency) {
            if ($currency === 'KES') continue;
            $currentMap[$currency] = $fxService->convertFromKes($kesAmount, $currency, $smartRound);
        }

        PlatformSetting::updateOrCreate(
            ['key' => 'min_payout_by_currency'],
            ['value' => json_encode($currentMap)]
        );

        return $this->apiSuccess('All currency payout thresholds auto-synced successfully from KES amount', [
            'base_kes_amount'        => $kesAmount,
            'currency'               => 'KES',
            'min_payout_amount'      => $kesAmount,
            'min_payout_by_currency' => $currentMap,
        ]);
    }
}