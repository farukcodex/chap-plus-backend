<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantReview extends Model
{
    protected $fillable = ['merchant_profile_id', 'user_id', 'rating', 'comment'];

    public function merchantProfile()
    {
        return $this->belongsTo(MerchantProfile::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
