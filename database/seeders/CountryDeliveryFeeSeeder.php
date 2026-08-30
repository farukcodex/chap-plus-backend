<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CountryDeliveryFeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $fees = [
            ['country' => 'KE', 'fee_amount' => 150.00, 'currency' => 'KES'],
            ['country' => 'TZ', 'fee_amount' => 2500.00, 'currency' => 'TZS'],
            ['country' => 'UG', 'fee_amount' => 3500.00, 'currency' => 'UGX'],
            ['country' => 'RW', 'fee_amount' => 1000.00, 'currency' => 'RWF'],
            ['country' => 'US', 'fee_amount' => 5.00, 'currency' => 'USD'],
        ];

        foreach ($fees as $fee) {
            \App\Models\CountryDeliveryFee::updateOrCreate(
                ['country' => $fee['country']],
                ['fee_amount' => $fee['fee_amount'], 'currency' => $fee['currency']]
            );
        }
    }
}
