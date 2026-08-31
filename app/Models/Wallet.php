<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $fillable = ['user_id', 'balance', 'currency'];

    protected static function booted()
    {
        static::creating(function ($wallet) {
            if (!$wallet->currency) {
                $user = $wallet->user;
                if ($user && $user->riderProfile && $user->riderProfile->currency) {
                    $wallet->currency = $user->riderProfile->currency;
                } elseif ($user && $user->merchantProfile && $user->merchantProfile->currency) {
                    $wallet->currency = $user->merchantProfile->currency;
                } else {
                    $wallet->currency = \App\Models\PlatformSetting::where('key', 'currency')->value('value') ?? 'KES';
                }
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
