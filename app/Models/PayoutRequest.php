<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayoutRequest extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'status',
        'payout_method',
        'mpesa_number',
        'payment_mode',
        'transaction_reference',
        'mpesa_conversation_id',
        'mpesa_originator_conversation_id',
        'processed_by_admin_id',
        'admin_notes',
        'rejection_reason',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'processed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function processedByAdmin()
    {
        return $this->belongsTo(User::class, 'processed_by_admin_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }
}
