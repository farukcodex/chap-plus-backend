<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;
    protected $fillable = [
        'order_number', 'user_id', 'merchant_profile_id', 'type', 'total_amount', 'delivery_fee', 
        'status', 'user_address_id', 'payment_method', 
        'mpesa_checkout_request_id', 'mpesa_receipt_number',
        'rider_id', 'cancellation_reason', 'rating', 'review_comment',
        'delivery_otp', 'distance_km', 'duration_minute',
    ];

    public function scopeEcommerce($query)
    {
        return $query->where('type', 'ecommerce');
    }

    public function scopeRestaurant($query)
    {
        return $query->where('type', 'restaurant');
    }

    protected static function booted(): void
    {
        static::created(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = '#ORD-' . str_pad($order->id, 5, '0', STR_PAD_LEFT);
                $order->saveQuietly();
            }
        });
    }

    public function address()
    {
        return $this->belongsTo(UserAddress::class, 'user_address_id');
    }

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
