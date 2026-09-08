<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $primaryImage = $this->images?->firstWhere('is_primary', true)?->image_url
            ?? $this->images?->first()?->image_url;

        return [
            'id'                   => (int) $this->id,
            'name'                 => (string) $this->name,
            'description'          => $this->description,
            'bus_type'             => (string) $this->bus_type,
            'price_per_seat'       => (float) $this->price_per_seat,
            'currency'             => (string) ($this->merchantProfile?->currency ?? 'USD'),
            'departure_place'      => (string) $this->departure_place,
            'departure_time'       => (string) $this->departure_time,
            'destination_place'    => (string) $this->destination_place,
            'destination_time'     => (string) $this->destination_time,
            'journey_duration'     => $this->journey_duration,
            'rest_place'           => $this->rest_place,
            'rest_duration'        => $this->rest_duration,
            'seat_pattern'         => (string) $this->seat_pattern,
            'driver_position'      => (string) $this->driver_position,
            'total_rows'           => (int) $this->total_rows,
            'back_row_seats'       => (int) $this->back_row_seats,
            'total_bookable_seats' => (int) $this->total_bookable_seats,
            'has_middle_door'      => (bool) $this->has_middle_door,
            'facilities'           => is_array($this->facilities) ? $this->facilities : [],

            // Date-specific availability (calculated when travel_date is provided)
            'travel_date'           => $this->travel_date ?? null,
            'available_seats_count' => isset($this->available_seats_count) ? (int) $this->available_seats_count : (int) $this->total_bookable_seats,
            'booked_seats_count'    => isset($this->booked_seats_count) ? (int) $this->booked_seats_count : 0,
            'is_sold_out'           => isset($this->is_sold_out) ? (bool) $this->is_sold_out : false,

            // Images
            'primary_image'        => $primaryImage,
            'images'               => $this->images ? $this->images->map(fn($img) => [
                'id'         => (int) $img->id,
                'image_url'  => $img->image_url,
                'is_primary' => (bool) $img->is_primary,
            ])->values() : [],

            // Public Operator Information (no merchant auth credentials leaked)
            'operator'             => [
                'id'            => (int) ($this->merchantProfile?->id ?? 0),
                'business_name' => $this->merchantProfile?->business_name ?? 'Bus Operator',
                'phone_number'  => $this->merchantProfile?->phone_number,
                'city'          => $this->merchantProfile?->city,
                'country'       => $this->merchantProfile?->country,
                'profile_image' => $this->merchantProfile?->profile_image_url,
            ],
        ];
    }
}
