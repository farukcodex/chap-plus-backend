<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CountryDeliveryFee;
use App\Models\PlatformSetting;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use League\ISO3166\Exception\OutOfBoundsException;
use League\ISO3166\ISO3166;

class DeliveryFeeController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get configured delivery fees per country,
     * along with available countries (Option 1: East Africa prioritized) and currencies.
     */
    public function index(): JsonResponse
    {
        $iso = new ISO3166();
        $all = $iso->all();

        // 1. Configured delivery fees
        $configuredFees = CountryDeliveryFee::orderBy('country')->get();
        $minPayouts = PlatformSetting::getMinPayoutByCurrency();

        $deliveryFees = $configuredFees->map(function ($item) use ($iso, $minPayouts) {
            $countryName = $item->country;
            try {
                $isoData = $iso->alpha2(strtoupper($item->country));
                $countryName = $isoData['name'] ?? $item->country;
            } catch (OutOfBoundsException $e) {
                // Unknown code fallback
            }

            $currency = strtoupper((string) ($item->currency ?: 'USD'));
            $minPayout = $minPayouts[$currency] ?? PlatformSetting::getMinPayoutForCurrency($currency);

            return [
                'id'                => $item->id,
                'country'           => strtoupper($item->country),
                'country_name'      => $countryName,
                'currency'          => $currency,
                'delivery_fee'      => (float) $item->fee_amount,
                'min_payout_amount' => (float) $minPayout,
                'created_at'        => $item->created_at,
                'updated_at'        => $item->updated_at,
            ];
        })->values()->all();

        // Sort delivery fees: East Africa + US priority first, then alphabetical by country name
        $priorityCodes = ['KE', 'TZ', 'UG', 'RW', 'BI', 'SS', 'US'];
        $priorityFees = [];
        $otherFees = [];
        foreach ($deliveryFees as $fee) {
            $code = $fee['country'];
            if (in_array($code, $priorityCodes)) {
                $priorityFees[$code] = $fee;
            } else {
                $otherFees[] = $fee;
            }
        }
        $sortedPriority = [];
        foreach ($priorityCodes as $code) {
            if (isset($priorityFees[$code])) {
                $sortedPriority[] = $priorityFees[$code];
            }
        }
        usort($otherFees, fn($a, $b) => strcmp($a['country_name'], $b['country_name']));
        $orderedDeliveryFees = array_merge($sortedPriority, $otherFees);

        // 2. Available countries with East Africa prioritized (Option 1)
        $byCode = [];
        $allCurrencies = [];

        foreach ($all as $c) {
            $code = strtoupper($c['alpha2']);
            $currency = !empty($c['currency']) ? $c['currency'][0] : null;
            $byCode[$code] = [
                'code'     => $code,
                'name'     => $c['name'],
                'currency' => $currency,
            ];

            if (!empty($c['currency'])) {
                foreach ($c['currency'] as $curr) {
                    $allCurrencies[$curr] = true;
                }
            }
        }

        $priorityList = [];
        foreach ($priorityCodes as $code) {
            if (isset($byCode[$code])) {
                $priorityList[] = $byCode[$code];
                unset($byCode[$code]);
            }
        }

        $otherList = array_values($byCode);
        usort($otherList, fn($a, $b) => strcmp($a['name'], $b['name']));

        $availableCountries = array_merge($priorityList, $otherList);

        // 3. Available currencies list
        ksort($allCurrencies);
        $availableCurrencies = array_keys($allCurrencies);

        return $this->apiSuccess('Delivery fees retrieved successfully', [
            'delivery_fees'       => $orderedDeliveryFees,
            'available_countries' => $availableCountries,
            'available_currencies'=> $availableCurrencies,
        ]);
    }

    /**
     * Create or update a delivery fee for a country.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = validator($request->all(), [
            'country'           => 'required|string|size:2',
            'delivery_fee'      => 'required|numeric|min:0',
            'currency'          => 'nullable|string|size:3',
            'min_payout_amount' => 'nullable|numeric|min:0',
        ])->validate();

        $countryCode = strtoupper(trim($validated['country']));
        $iso = new ISO3166();
        try {
            $isoData = $iso->alpha2($countryCode);
        } catch (OutOfBoundsException $e) {
            return $this->apiError('The provided country code is not a valid ISO 3166-1 alpha-2 code.', 422, [
                'country' => ['Invalid country code.']
            ]);
        }

        $currency = !empty($validated['currency'])
            ? strtoupper(trim($validated['currency']))
            : (!empty($isoData['currency'][0]) ? $isoData['currency'][0] : 'USD');

        $deliveryFee = (float) $validated['delivery_fee'];

        // 1. Update or create CountryDeliveryFee
        $feeRecord = CountryDeliveryFee::updateOrCreate(
            ['country' => $countryCode],
            [
                'fee_amount' => $deliveryFee,
                'currency'   => $currency,
            ]
        );

        // 2. Optionally sync min_payout_amount for this currency
        if (isset($validated['min_payout_amount'])) {
            $minPayoutAmount = (float) $validated['min_payout_amount'];
            $currentMap = PlatformSetting::getMinPayoutByCurrency();
            $currentMap[$currency] = $minPayoutAmount;
            PlatformSetting::updateOrCreate(
                ['key' => 'min_payout_by_currency'],
                ['value' => json_encode($currentMap)]
            );

            $defaultCurrency = strtoupper((string) (PlatformSetting::where('key', 'currency')->value('value') ?? 'KES'));
            if ($currency === $defaultCurrency) {
                PlatformSetting::updateOrCreate(
                    ['key' => 'min_payout_amount'],
                    ['value' => (string) $minPayoutAmount]
                );
            }
        }

        $minPayout = PlatformSetting::getMinPayoutForCurrency($currency);

        return $this->apiSuccess('Delivery fee saved successfully', [
            'id'                => $feeRecord->id,
            'country'           => $countryCode,
            'country_name'      => $isoData['name'] ?? $countryCode,
            'currency'          => $currency,
            'delivery_fee'      => $deliveryFee,
            'min_payout_amount' => (float) $minPayout,
            'created_at'        => $feeRecord->created_at,
            'updated_at'        => $feeRecord->updated_at,
        ]);
    }

    /**
     * Update an existing delivery fee by ID or Country code.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $feeRecord = is_numeric($id)
            ? CountryDeliveryFee::find($id)
            : CountryDeliveryFee::where('country', strtoupper(trim((string) $id)))->first();

        if (!$feeRecord) {
            return $this->apiError('Delivery fee record not found', 404);
        }

        $validated = validator($request->all(), [
            'delivery_fee'      => 'nullable|numeric|min:0',
            'currency'          => 'nullable|string|size:3',
            'min_payout_amount' => 'nullable|numeric|min:0',
        ])->validate();

        if (isset($validated['delivery_fee'])) {
            $feeRecord->fee_amount = (float) $validated['delivery_fee'];
        }

        if (!empty($validated['currency'])) {
            $feeRecord->currency = strtoupper(trim($validated['currency']));
        }

        $feeRecord->save();

        if (isset($validated['min_payout_amount'])) {
            $currency = $feeRecord->currency;
            $minPayoutAmount = (float) $validated['min_payout_amount'];
            $currentMap = PlatformSetting::getMinPayoutByCurrency();
            $currentMap[$currency] = $minPayoutAmount;
            PlatformSetting::updateOrCreate(
                ['key' => 'min_payout_by_currency'],
                ['value' => json_encode($currentMap)]
            );

            $defaultCurrency = strtoupper((string) (PlatformSetting::where('key', 'currency')->value('value') ?? 'KES'));
            if ($currency === $defaultCurrency) {
                PlatformSetting::updateOrCreate(
                    ['key' => 'min_payout_amount'],
                    ['value' => (string) $minPayoutAmount]
                );
            }
        }

        $iso = new ISO3166();
        $countryName = $feeRecord->country;
        try {
            $isoData = $iso->alpha2(strtoupper($feeRecord->country));
            $countryName = $isoData['name'] ?? $feeRecord->country;
        } catch (OutOfBoundsException $e) {
        }

        $minPayout = PlatformSetting::getMinPayoutForCurrency($feeRecord->currency);

        return $this->apiSuccess('Delivery fee updated successfully', [
            'id'                => $feeRecord->id,
            'country'           => $feeRecord->country,
            'country_name'      => $countryName,
            'currency'          => $feeRecord->currency,
            'delivery_fee'      => (float) $feeRecord->fee_amount,
            'min_payout_amount' => (float) $minPayout,
            'created_at'        => $feeRecord->created_at,
            'updated_at'        => $feeRecord->updated_at,
        ]);
    }

    /**
     * Delete a delivery fee rule by ID or Country code.
     */
    public function destroy($id): JsonResponse
    {
        $feeRecord = is_numeric($id)
            ? CountryDeliveryFee::find($id)
            : CountryDeliveryFee::where('country', strtoupper(trim((string) $id)))->first();

        if (!$feeRecord) {
            return $this->apiError('Delivery fee record not found', 404);
        }

        $countryCode = $feeRecord->country;
        $feeRecord->delete();

        return $this->apiSuccess("Delivery fee rule for '{$countryCode}' deleted successfully");
    }

    /**
     * Auto-sync all 250 world countries' delivery fees based on a single base KES amount using live forex rates.
     */
    public function autoSync(Request $request, \App\Services\ExchangeRateService $fxService): JsonResponse
    {
        $validated = validator($request->all(), [
            'kes_amount' => 'required|numeric|min:1',
            'rounding'   => 'nullable|boolean',
        ])->validate();

        $kesAmount = (float) $validated['kes_amount'];
        $smartRound = $request->boolean('rounding', true);
        $now = now();

        $iso = new ISO3166();
        $allCountries = $iso->all();

        $rows = [];
        foreach ($allCountries as $c) {
            $countryCode = strtoupper(trim($c['alpha2']));
            $currency = !empty($c['currency'][0]) ? strtoupper(trim($c['currency'][0])) : 'USD';
            $convertedFee = $fxService->convertFromKes($kesAmount, $currency, $smartRound);

            $rows[] = [
                'country'    => $countryCode,
                'fee_amount' => $convertedFee,
                'currency'   => $currency,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        CountryDeliveryFee::upsert($rows, ['country'], ['fee_amount', 'currency', 'updated_at']);

        $allFees = $this->index()->getData(true)['data']['delivery_fees'] ?? [];

        return $this->apiSuccess('All 250 countries delivery fees auto-synced successfully from KES amount', [
            'base_kes_amount' => $kesAmount,
            'synced_count'    => count($rows),
            'delivery_fees'   => $allFees,
        ]);
    }
}