<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $roleName = $this->roles->first()?->name ?? 'USER';

        $isRider = ($roleName === 'RIDER') || ($this->riderProfile && !$this->merchantProfile && !$this->userProfile);
        $isMerchant = in_array($roleName, ['BUS_MERCHANT', 'RESTAURANT_MERCHANT', 'ECOMMERCE_MERCHANT', 'HOTEL_MERCHANT']) || ($this->merchantProfile && !$this->riderProfile && !$this->userProfile);

        $activeProfile = $isRider 
            ? ($this->riderProfile ?? $this->userProfile) 
            : ($isMerchant ? ($this->merchantProfile ?? $this->userProfile) : $this->userProfile);

        $dob = $activeProfile?->date_of_birth 
            ? ($activeProfile->date_of_birth instanceof \DateTimeInterface ? $activeProfile->date_of_birth->format('Y-m-d') : (string) $activeProfile->date_of_birth)
            : ($activeProfile?->dob ?? null);

        $data = [
            'id'                => (int) $this->id,
            'name'              => (string) $this->name,
            'email'             => (string) $this->email,
            'role'              => (string) $roleName,
            'phone_number'      => (string) ($activeProfile?->phone_number ?? 'N/A'),
            'city'              => $activeProfile?->city,
            'country'           => $activeProfile?->country,
            'address'           => $activeProfile?->address,
            'gender'            => $activeProfile?->gender ?? 'N/A',
            'date_of_birth'     => $dob,
            'is_blocked'        => (bool) $this->is_blocked,
            'profile_photo_url' => $this->profile_photo_url,
            'email_verified_at' => $this->email_verified_at?->toISOString() ?? $this->email_verified_at,
            'created_at'        => $this->created_at?->toISOString() ?? $this->created_at,
        ];

        if ($isRider && $this->riderProfile) {
            $data['rider_profile'] = [
                'phone_number' => (string) ($this->riderProfile->phone_number ?? 'N/A'),
                'city'         => $this->riderProfile->city,
                'country'      => $this->riderProfile->country,
                'address'      => $this->riderProfile->address,
                'gender'       => $this->riderProfile->gender,
                'dob'          => $this->riderProfile->dob,
                'status'       => $this->riderProfile->status,
            ];
        } elseif ($isMerchant && $this->merchantProfile) {
            $data['merchant_profile'] = [
                'business_name' => $this->merchantProfile->business_name,
                'merchant_type' => $this->merchantProfile->merchant_type,
                'phone_number'  => (string) ($this->merchantProfile->phone_number ?? 'N/A'),
                'city'          => $this->merchantProfile->city,
                'country'       => $this->merchantProfile->country,
                'address'       => $this->merchantProfile->address,
                'status'        => $this->merchantProfile->status,
            ];
        } else {
            $up = $this->userProfile;
            $data['user_profile'] = $up ? [
                'phone_number'  => (string) ($up->phone_number ?? 'N/A'),
                'city'          => $up->city,
                'country'       => $up->country,
                'address'       => $up->address,
                'gender'        => $up->gender,
                'date_of_birth' => $up->date_of_birth ? ($up->date_of_birth instanceof \DateTimeInterface ? $up->date_of_birth->format('Y-m-d') : (string) $up->date_of_birth) : null,
            ] : null;
        }

        $data['wallet'] = [
            'balance'  => $this->wallet ? (float) $this->wallet->balance : 0.0,
            'currency' => $this->wallet?->currency ?? 'USD',
        ];
        $data['activity_summary'] = [
            'total_orders'         => (int) ($this->total_orders ?? 0),
            'total_bus_bookings'   => (int) ($this->total_bus_bookings ?? 0),
            'total_hotel_bookings' => (int) ($this->total_hotel_bookings ?? 0),
        ];
        $data['addresses'] = $this->addresses ?? [];

        return $data;
    }
}
