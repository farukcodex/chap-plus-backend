<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PlatformSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            ['key' => 'merchant_commission_percent', 'value' => '10.00'],
            ['key' => 'rider_commission_percent', 'value' => '0.00'],
            ['key' => 'min_payout_amount', 'value' => '500.00'],
            ['key' => 'currency', 'value' => 'KES'],
        ];

        foreach ($settings as $setting) {
            \App\Models\PlatformSetting::updateOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value']]
            );
        }
    }
}
