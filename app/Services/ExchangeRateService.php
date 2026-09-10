<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExchangeRateService
{
    /**
     * Fallback rates (1 KES = X currency) in case live endpoint is unreachable.
     */
    protected const FALLBACK_KES_RATES = [
        'KES' => 1.0,
        'USD' => 0.00772,
        'TZS' => 20.427,
        'UGX' => 29.225,
        'RWF' => 11.380,
        'BIF' => 23.131,
        'SSP' => 43.652,
        'EUR' => 0.00665,
        'GBP' => 0.00571,
    ];

    /**
     * Get live exchange rates with KES as the base currency.
     * Caches in memory/database for 1 hour to optimize performance.
     *
     * @return array<string, float>
     */
    public function getRatesFromKes(): array
    {
        return Cache::remember('fx_rates_kes', 3600, function () {
            try {
                $response = Http::timeout(6)->get('https://open.er-api.com/v6/latest/KES');
                if ($response->successful()) {
                    $json = $response->json();
                    if (isset($json['rates']) && is_array($json['rates'])) {
                        $rates = [];
                        foreach ($json['rates'] as $curr => $rate) {
                            $rates[strtoupper((string) $curr)] = (float) $rate;
                        }
                        return $rates;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to fetch live KES exchange rates, using fallback: ' . $e->getMessage());
            }

            return self::FALLBACK_KES_RATES;
        });
    }

    /**
     * Convert an amount from KES to a target currency with smart consumer rounding.
     *
     * @param float $kesAmount
     * @param string $targetCurrency
     * @param bool $smartRound
     * @return float
     */
    public function convertFromKes(float $kesAmount, string $targetCurrency, bool $smartRound = true): float
    {
        $currency = strtoupper(trim($targetCurrency));
        if ($currency === 'KES') {
            return (float) $kesAmount;
        }

        $rates = $this->getRatesFromKes();
        $rate = $rates[$currency] ?? (self::FALLBACK_KES_RATES[$currency] ?? null);

        if (!$rate) {
            // If unknown currency, try fallback through USD if available
            $usdRate = $rates['USD'] ?? self::FALLBACK_KES_RATES['USD'];
            $rate = $usdRate; // safe conservative fallback
        }

        $raw = $kesAmount * $rate;

        if (!$smartRound) {
            return round($raw, 2);
        }

        // Smart consumer financial rounding
        if ($raw >= 1000) {
            return (float) (round($raw / 100) * 100);
        } elseif ($raw >= 100) {
            return (float) (round($raw / 10) * 10);
        } elseif ($raw >= 10) {
            return (float) round($raw);
        } else {
            return (float) round($raw, 2);
        }
    }
}