<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    protected $fillable = [
        'user_id', 'country', 'city', 'currency', 
        'phone_number', 'gender', 'date_of_birth', 'address',
        'latitude', 'longitude'
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'latitude'      => 'float',
        'longitude'     => 'float',
    ];

    protected $appends = ['lat', 'lon'];

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
}
