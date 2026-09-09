<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class RiderProfile extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id', 'phone_number', 'gender', 'dob', 'address',
        'latitude', 'longitude',
        'country', 'city', 'currency',
        'license_image_path', 'national_id_image_path',
        'mpesa_payout_number', 'status'
    ];

    protected $casts = [
        'dob'       => 'date',
        'latitude'  => 'float',
        'longitude' => 'float',
    ];

    protected $appends = [
        'license_image_url',
        'national_id_image_url',
        'lat',
        'lon',
    ];

    public function getLatAttribute(): ?float
    {
        return $this->latitude !== null ? (float) $this->latitude : null;
    }

    public function getLonAttribute(): ?float
    {
        return $this->longitude !== null ? (float) $this->longitude : null;
    }

    public function setLatAttribute($value): void
    {
        $this->attributes['latitude'] = $value;
    }

    public function setLonAttribute($value): void
    {
        $this->attributes['longitude'] = $value;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getLicenseImageUrlAttribute()
    {
        return $this->license_image_path ? asset('storage/' . $this->license_image_path) : null;
    }

    public function getNationalIdImageUrlAttribute()
    {
        return $this->national_id_image_path ? asset('storage/' . $this->national_id_image_path) : null;
    }
}
