<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FavoriteRestaurant extends Model
{
    protected $fillable = ['user_id', 'merchant_profile_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function merchantProfile()
    {
        return $this->belongsTo(MerchantProfile::class);
    }
}
