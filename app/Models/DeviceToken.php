<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token',
        'platform',
        'device_name',
        'device_id',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * The user that owns this device token.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
