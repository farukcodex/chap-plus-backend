<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bus extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'facilities' => 'array',
        'has_middle_door' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function images()
    {
        return $this->hasMany(BusImage::class);
    }

    public function merchantProfile()
    {
        return $this->belongsTo(MerchantProfile::class);
    }

    public function bookings()
    {
        return $this->hasMany(BusBooking::class);
    }
}
