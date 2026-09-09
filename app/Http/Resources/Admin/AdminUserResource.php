<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $roleName = $this->roles->first()?->name ?? 'USER';

        // Identify account role type
        $isRider = ($roleName === 'RIDER') || ($this->riderProfile && !$this->merchantProfile && !$this->userProfile);
        $isMerchant = in_array($roleName, ['BUS_MERCHANT', 'RESTAURANT_MERCHANT', 'ECOMMERCE_MERCHANT', 'HOTEL_MERCHANT']) || ($this->merchantProfile && !$this->riderProfile && !$this->userProfile);

        $activeProfile = $isRider 
            ? ($this->riderProfile ?? $this->userProfile) 
            : ($isMerchant ? ($this->merchantProfile ?? $this->userProfile) : $this->userProfile);

        $data = [
            'id'                => (int) $this->id,
            'name'              => (string) $this->name,
            'email'             => (string) $this->email,
            'role'              => (string) $roleName,
            'phone_number'      => (string) ($activeProfile?->phone_number ?? 'N/A'),
            'city'              => $activeProfile?->city,
            'country'           => $activeProfile?->country,
            'is_blocked'        => (bool) $this->is_blocked,
            'profile_photo_url' => $this->profile_photo_url,
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

        return $data;
    }
}
