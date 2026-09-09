<?php

namespace App\Http\Resources\Admin;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminPayoutResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->user;
        $admin = $this->processedByAdmin;
        $merchantProfile = $user?->merchantProfile;
        $riderProfile = $user?->riderProfile;

        // Determine user role / type
        $roleName = 'USER';
        if ($user) {
            $roleNames = $user->getRoleNames();
            $roleName = $roleNames->first() ?? 'USER';
        }

        return [
            'id'                               => (int) $this->id,
            'amount'                           => (float) $this->amount,
            'status'                           => (string) $this->status,
            'payout_method'                    => (string) $this->payout_method,
            'mpesa_number'                     => (string) $this->mpesa_number,
            'payment_mode'                     => $this->payment_mode,
            'transaction_reference'            => $this->transaction_reference,
            'mpesa_conversation_id'            => $this->mpesa_conversation_id,
            'mpesa_originator_conversation_id' => $this->mpesa_originator_conversation_id,
            'admin_notes'                      => $this->admin_notes,
            'rejection_reason'                 => $this->rejection_reason,
            'processed_at'                     => $this->processed_at ? Carbon::parse($this->processed_at)->toIso8601String() : null,
            'created_at'                       => $this->created_at ? Carbon::parse($this->created_at)->toIso8601String() : null,

            // Requester details
            'requester' => $user ? [
                'id'             => (int) $user->id,
                'name'           => (string) $user->name,
                'email'          => (string) $user->email,
                'phone'          => (string) ($user->phone ?? $riderProfile?->phone_number ?? ''),
                'role'           => $roleName,
                'profile_photo'  => $user->profile_photo_url,
                'wallet_balance' => (float) ($user->wallet?->balance ?? 0),
                'merchant'       => $merchantProfile ? [
                    'id'            => (int) $merchantProfile->id,
                    'business_name' => (string) $merchantProfile->business_name,
                    'phone_number'  => $merchantProfile->phone_number,
                    'city'          => $merchantProfile->city,
                ] : null,
                'rider'          => $riderProfile ? [
                    'id'           => (int) $riderProfile->id,
                    'phone_number' => $riderProfile->phone_number,
                    'city'         => $riderProfile->city,
                ] : null,
            ] : null,

            // Admin who processed
            'processed_by' => $admin ? [
                'id'    => (int) $admin->id,
                'name'  => (string) $admin->name,
                'email' => (string) $admin->email,
            ] : null,
        ];
    }
}
