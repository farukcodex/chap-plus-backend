<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CountryDeliveryFee extends Model
{
    protected $fillable = ['country', 'fee_amount', 'currency'];
}
