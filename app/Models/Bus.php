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

    protected $appends = ['journey_duration'];

    public function getJourneyDurationAttribute(): ?string
    {
        if (!$this->departure_time || !$this->destination_time) {
            return null;
        }

        try {
            $dep = \Carbon\Carbon::createFromFormat('H:i', $this->departure_time);
            $dest = \Carbon\Carbon::createFromFormat('H:i', $this->destination_time);

            if ($dest->lessThan($dep)) {
                $dest->addDay();
            }

            $diffMinutes = $dep->diffInMinutes($dest);
            $hours = intdiv($diffMinutes, 60);
            $minutes = $diffMinutes % 60;

            if ($hours > 0 && $minutes > 0) {
                return "{$hours}h {$minutes}m";
            } elseif ($hours > 0) {
                return "{$hours}h";
            } else {
                return "{$minutes}m";
            }
        } catch (\Exception $e) {
            return null;
        }
    }

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
