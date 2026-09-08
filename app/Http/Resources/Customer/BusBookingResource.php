<?php

namespace App\Http\Resources\Customer;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusBookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $bus = $this->bus;
        $merchant = $this->merchantProfile ?? $bus?->merchantProfile;

        $seatNumbers = is_array($this->seat_numbers) ? $this->seat_numbers : [];
        $travelDate = $this->travel_date instanceof Carbon 
            ? $this->travel_date->format('Y-m-d') 
            : ($this->travel_date ? Carbon::parse($this->travel_date)->format('Y-m-d') : null);

        $travelDateFormatted = $this->travel_date
            ? Carbon::parse($this->travel_date)->format('D, M d, Y')
            : null;

        // Seat lock countdown calculation
        $lockedRemainingSeconds = 0;
        $isLocked = false;
        if ($this->status === 'pending_payment' && $this->locked_until) {
            $lockedUntil = Carbon::parse($this->locked_until);
            if ($lockedUntil->isFuture()) {
                $lockedRemainingSeconds = (int) max(0, Carbon::now()->diffInSeconds($lockedUntil, false));
                $isLocked = true;
            }
        }

        // Primary bus image
        $primaryImage = $bus?->images?->firstWhere('is_primary', true)?->image_url
            ?? $bus?->images?->first()?->image_url;

        return [
            'id'                       => (int) $this->id,
            'booking_reference'        => '#BUS-' . str_pad($this->id, 5, '0', STR_PAD_LEFT),
            'travel_date'              => $travelDate,
            'travel_date_formatted'    => $travelDateFormatted,
            'seat_numbers'             => $seatNumbers,
            'seat_count'               => count($seatNumbers),
            'passenger_name'           => (string) $this->passenger_name,
            'passenger_phone'          => (string) $this->passenger_phone,
            'passenger_email'          => $this->passenger_email,
            'total_price'              => (float) $this->total_price,
            'currency'                 => (string) ($merchant?->currency ?? 'USD'),
            'payment_method'           => (string) $this->payment_method,
            'status'                   => (string) $this->status,
            'mpesa_receipt_number'     => $this->mpesa_receipt_number,
            'locked_until'             => $this->locked_until ? Carbon::parse($this->locked_until)->toIso8601String() : null,
            'locked_seconds_remaining' => $lockedRemainingSeconds,
            'is_locked'                => $isLocked,
            'ticket_url'               => $this->status === 'paid' 
                ? url("/api/buses/bookings/{$this->id}/ticket") 
                : null,

            // Clean Embedded Bus Route Info
            'bus' => $bus ? [
                'id'                => (int) $bus->id,
                'name'              => (string) $bus->name,
                'bus_type'          => (string) $bus->bus_type,
                'departure_place'   => (string) $bus->departure_place,
                'departure_time'    => (string) $bus->departure_time,
                'destination_place' => (string) $bus->destination_place,
                'destination_time'  => (string) $bus->destination_time,
                'journey_duration'  => $bus->journey_duration,
                'primary_image'     => $primaryImage,
            ] : null,

            // Clean Operator Info (NO merchant user auth credentials leaked)
            'operator' => $merchant ? [
                'id'            => (int) $merchant->id,
                'business_name' => (string) $merchant->business_name,
                'phone_number'  => $merchant->phone_number,
                'city'          => $merchant->city,
                'country'       => $merchant->country,
                'profile_image' => $merchant->profile_image_url,
            ] : null,

            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toIso8601String() : null,
        ];
    }
}
