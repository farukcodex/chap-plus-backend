<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayoutRequest extends Model
{
    protected $fillable = ['user_id', 'amount', 'status', 'payout_method', 'mpesa_number'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
