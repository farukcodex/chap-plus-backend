<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'refundable_type',
        'refundable_id',
        'amount',
        'status',
        'mpesa_conversation_id',
        'mpesa_receipt_number',
        'processed_by_admin_id',
        'admin_notes',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function refundable()
    {
        return $this->morphTo();
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by_admin_id');
    }
}
