<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 
    'country', 
    'city', 
    'currency',
    'business_name', 
    'address', 
    'description', 
    'profile_image_path', 
    'cover_image_path'
])]
class MerchantProfile extends Model
{
    protected $appends = ['profile_image_url', 'cover_image_url'];
    protected $hidden = ['profile_image_path', 'cover_image_path'];

    /**
     * Get the user that owns the merchant profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getProfileImageUrlAttribute()
    {
        if (!$this->profile_image_path) {
            return null;
        }
        if (\Illuminate\Support\Str::startsWith($this->profile_image_path, ['http://', 'https://'])) {
            return $this->profile_image_path;
        }
        return asset('storage/' . $this->profile_image_path);
    }

    public function getCoverImageUrlAttribute()
    {
        if (!$this->cover_image_path) {
            return null;
        }
        if (\Illuminate\Support\Str::startsWith($this->cover_image_path, ['http://', 'https://'])) {
            return $this->cover_image_path;
        }
        return asset('storage/' . $this->cover_image_path);
    }
}
