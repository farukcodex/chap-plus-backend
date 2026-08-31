<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id', 'merchant_profile_id', 'total_amount', 'delivery_fee', 
        'status', 'delivery_address', 'payment_method', 
        'mpesa_checkout_request_id', 'mpesa_receipt_number', 'customer_phone_number',
        'rider_id', 'cancellation_reason', 'rating', 'review_comment',
        'delivery_otp',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function merchantProfile()
    {
        return $this->belongsTo(MerchantProfile::class);
    }

    public function rider()
    {
        return $this->belongsTo(User::class, 'rider_id');
    }
}
