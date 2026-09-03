<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Hotel extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_profile_id',
        'name',
        'description',
        'price_per_night',
        'room_quantity',
        'facilities',
        'is_active',
    ];

    protected $casts = [
        'facilities' => 'array',
        'is_active' => 'boolean',
        'price_per_night' => 'decimal:2',
    ];

    public function merchantProfile()
    {
        return $this->belongsTo(MerchantProfile::class);
    }

    public function images()
    {
        return $this->hasMany(HotelImage::class);
    }

    public function reviews()
    {
        return $this->hasMany(HotelReview::class);
    }

    public function bookings()
    {
        return $this->hasMany(HotelBooking::class);
    }

    /**
     * Calculate how many rooms are available for a given date range.
     * Prevents double booking by checking existing active bookings.
     */
    public function getAvailableRooms(string $checkIn, string $checkOut): int
    {
        // A booking is "active" (blocking a room) if it is:
        // 1. paid or checked_in
        // 2. pending_payment AND created within the last 15 minutes (soft lock)
        
        $lockCutoff = Carbon::now()->subMinutes(15);

        $bookedRooms = $this->bookings()
            ->where(function ($q) use ($lockCutoff) {
                $q->whereIn('status', ['paid', 'checked_in'])
                  ->orWhere(function ($sub) use ($lockCutoff) {
                      $sub->where('status', 'pending_payment')
                          ->where('created_at', '>=', $lockCutoff);
                  });
            })
            // Date overlap logic: (Existing Check-In < Requested Check-Out) AND (Existing Check-Out > Requested Check-In)
            ->where('check_in_date', '<', $checkOut)
            ->where('check_out_date', '>', $checkIn)
            ->sum('rooms_booked');

        $available = $this->room_quantity - $bookedRooms;

        return $available > 0 ? (int) $available : 0;
    }
}
