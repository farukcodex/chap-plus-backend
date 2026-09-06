<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusBooking extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'seat_numbers' => 'array',
        'travel_date' => 'date',
        'locked_until' => 'datetime',
    ];

    public function bus()
    {
        return $this->belongsTo(Bus::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
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
