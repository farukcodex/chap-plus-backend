<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiderProfile extends Model
{
    protected $fillable = [
        'user_id', 'phone_number', 'gender', 'dob', 'address',
        'license_image_path', 'national_id_image_path',
        'mpesa_payout_number', 'status'
    ];

    protected $appends = [
        'license_image_url',
        'national_id_image_url',
    ];

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
