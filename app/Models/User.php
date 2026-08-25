<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'phone', 'password', 'email_verified_at', 'otp_code', 'otp_verified_at', 'otp_expires_at', 'password_reset_token', 'password_reset_expires_at', 'profile_photo_path', 'is_blocked', 'google_id'])]
#[Hidden(['password', 'remember_token', 'otp_code', 'password_reset_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles, HasApiTokens;

    protected $appends = ['profile_photo_url'];

    public function getProfilePhotoUrlAttribute()
    {
        if (!$this->profile_photo_path) {
            return null;
        }

        if (\Illuminate\Support\Str::startsWith($this->profile_photo_path, ['http://', 'https://'])) {
            return $this->profile_photo_path;
        }

        return asset('storage/' . $this->profile_photo_path);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'        => 'datetime',
            'otp_verified_at'           => 'datetime',
            'otp_expires_at'            => 'datetime',
            'password_reset_expires_at' => 'datetime',
            'password'                  => 'hashed',
            'is_blocked'                => 'boolean',
        ];
    }
}
