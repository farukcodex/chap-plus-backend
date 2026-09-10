<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;
    protected $fillable = [
        'order_number', 'user_id', 'merchant_profile_id', 'type', 'total_amount', 'delivery_fee', 
        'merchant_commission_rate', 'admin_commission', 'merchant_earnings',
        'rider_commission_rate', 'rider_earnings', 'commission_settled_at',
        'status', 'user_address_id', 'payment_method', 
        'mpesa_checkout_request_id', 'mpesa_receipt_number',
        'rider_id', 'cancellation_reason', 'rating', 'review_comment',
        'delivery_otp', 'distance_km', 'duration_minute',
    ];

    protected $casts = [
        'total_amount'             => 'float',
        'delivery_fee'             => 'float',
        'merchant_commission_rate' => 'float',
        'admin_commission'         => 'float',
        'merchant_earnings'        => 'float',
        'rider_commission_rate'    => 'float',
        'rider_earnings'           => 'float',
        'commission_settled_at'    => 'datetime',
        'distance_km'              => 'float',
        'duration_minute'          => 'integer',
        'rating'                   => 'integer',
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
