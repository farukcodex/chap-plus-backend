<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HotelBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'hotel_id',
        'merchant_profile_id',
        'check_in_date',
        'check_out_date',
        'rooms_booked',
        'total_price',
        'customer_phone_number',
        'status',
        'mpesa_checkout_request_id',
        'mpesa_receipt_number'
    ];

    protected $casts = [
        'check_in_date' => 'date',
        'check_out_date' => 'date',
        'total_price' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function merchantProfile()
    {
        return $this->belongsTo(MerchantProfile::class);
    }

    public function refund()
    {
        return $this->morphOne(Refund::class, 'refundable');
    }
}
