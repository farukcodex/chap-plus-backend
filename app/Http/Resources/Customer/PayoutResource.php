<?php

namespace App\Http\Resources\Customer;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayoutResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                    => (int) $this->id,
            'amount'                => (float) $this->amount,
            'status'                => (string) $this->status,
            'payout_method'         => (string) $this->payout_method,
            'mpesa_number'          => (string) $this->mpesa_number,
            'payment_mode'          => $this->payment_mode,
            'transaction_reference' => $this->transaction_reference,
            'admin_notes'           => $this->admin_notes,
            'rejection_reason'      => $this->rejection_reason,
            'processed_at'          => $this->processed_at ? Carbon::parse($this->processed_at)->toIso8601String() : null,
            'created_at'            => $this->created_at ? Carbon::parse($this->created_at)->toIso8601String() : null,
        ];
    }
}
