<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public const DEFAULT_CURRENCY_MIN_PAYOUTS = [
        'KES' => 500.0,
        'USD' => 5.0,
        'TZS' => 10000.0,
        'UGX' => 15000.0,
        'RWF' => 5000.0,
    ];

    public const DEFAULT_COMMISSIONS = [
        'ecommerce_commission_percent'  => 10.0,
        'restaurant_commission_percent' => 10.0,
        'bus_commission_percent'        => 10.0,
        'hotel_commission_percent'      => 10.0,
        'rider_commission_percent'      => 5.0,
        'merchant_commission_percent'   => 10.0,
    ];

    /**
     * Get all commission percentages as numeric floats.
     *
     * @return array<string, float>
     */
    public static function getCommissionRates(): array
    {
        $keys = array_keys(self::DEFAULT_COMMISSIONS);
        $settings = static::whereIn('key', $keys)->pluck('value', 'key')->toArray();

        $rates = [];
        $globalMerchant = isset($settings['merchant_commission_percent']) && is_numeric($settings['merchant_commission_percent'])
            ? (float) $settings['merchant_commission_percent']
            : self::DEFAULT_COMMISSIONS['merchant_commission_percent'];

        foreach (self::DEFAULT_COMMISSIONS as $key => $defaultVal) {
            if (isset($settings[$key]) && is_numeric($settings[$key])) {
                $rates[$key] = (float) $settings[$key];
            } elseif ($key !== 'rider_commission_percent') {
                $rates[$key] = $globalMerchant;
            } else {
                $rates[$key] = (float) $defaultVal;
            }
        }

        return $rates;
    }

    /**
     * Get commission rate for a specific service or entity type.
     */
    public static function getCommissionRate(string $type): float
    {
        $normalized = strtolower(trim($type));
        $key = match ($normalized) {
            'ecommerce', 'product' => 'ecommerce_commission_percent',
            'restaurant', 'food'   => 'restaurant_commission_percent',
            'bus'                  => 'bus_commission_percent',
            'hotel'                => 'hotel_commission_percent',
            'rider'                => 'rider_commission_percent',
            default                => 'merchant_commission_percent',
        };

        $val = static::where('key', $key)->value('value');
        if ($val !== null && is_numeric($val)) {
            return (float) $val;
        }

        if ($key !== 'rider_commission_percent') {
            $fallback = static::where('key', 'merchant_commission_percent')->value('value');
            if ($fallback !== null && is_numeric($fallback)) {
                return (float) $fallback;
            }
        }

        return self::DEFAULT_COMMISSIONS[$key] ?? 10.0;
    }

    /**
     * Get the minimum payout amounts mapped by currency code.
     *
     * @return array<string, float>
     */
    public static function getMinPayoutByCurrency(): array
    {
        $raw = static::where('key', 'min_payout_by_currency')->value('value');
        $map = self::DEFAULT_CURRENCY_MIN_PAYOUTS;

        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $curr => $val) {
                    $map[strtoupper(trim((string) $curr))] = (float) $val;
                }
            }
        }

        // Sync default currency with min_payout_amount setting
        $primaryMin = static::where('key', 'min_payout_amount')->value('value');
        $defaultCurrency = strtoupper((string) (static::where('key', 'currency')->value('value') ?? 'KES'));
        if ($primaryMin !== null && is_numeric($primaryMin)) {
            $map[$defaultCurrency] = (float) $primaryMin;
        }

        return $map;
    }

    /**
     * Get the minimum payout threshold for a given currency code.
     */
    public static function getMinPayoutForCurrency(?string $currency = null): float
    {
        $defaultCurrency = strtoupper((string) (static::where('key', 'currency')->value('value') ?? 'KES'));
        $targetCurrency = strtoupper(trim((string) ($currency ?: $defaultCurrency)));

        $map = static::getMinPayoutByCurrency();

        if (isset($map[$targetCurrency])) {
            return (float) $map[$targetCurrency];
        }

        // Fallback to global setting or 100.0
        $primaryMin = static::where('key', 'min_payout_amount')->value('value');
        return $primaryMin !== null && is_numeric($primaryMin) ? (float) $primaryMin : 100.0;
    }
}
