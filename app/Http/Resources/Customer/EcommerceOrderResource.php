<?php

namespace App\Http\Resources\Customer;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EcommerceOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $merchant = $this->merchantProfile;
        $address = $this->address;

        $totalAmount = (float) $this->total_amount;
        $deliveryFee = (float) $this->delivery_fee;
        $grandTotal = (float) round($totalAmount + $deliveryFee, 2);

        $data = [
            'id'              => (int) $this->id,
            'order_number'    => (string) $this->order_number,
            'type'            => 'ecommerce',
            'status'          => (string) $this->status,
            'payment_method'  => (string) $this->payment_method,
            'currency'        => (string) ($merchant?->currency ?? 'KES'),
            'total_amount'    => $totalAmount,
            'delivery_fee'    => $deliveryFee,
            'grand_total'     => $grandTotal,
            'delivery_otp'    => $this->delivery_otp ? (string) $this->delivery_otp : null,
            'distance_km'     => $this->distance_km !== null ? (float) $this->distance_km : null,
            'duration_minute' => $this->duration_minute !== null ? (int) $this->duration_minute : null,
        ];

        // Clean Store summary
        $data['store'] = $merchant ? [
            'id'            => (int) $merchant->id,
            'business_name' => (string) $merchant->business_name,
            'address'       => $merchant->address,
            'city'          => $merchant->city,
            'country'       => $merchant->country,
            'phone_number'  => $merchant->phone_number,
            'profile_image' => $merchant->profile_image_url,
        ] : null;

        // Clean Delivery Address summary
        $data['delivery_address'] = $address ? [
            'id'           => (int) $address->id,
            'title'        => (string) $address->title,
            'address_text' => (string) $address->address_text,
            'phone_number' => (string) $address->phone_number,
            'latitude'     => $address->latitude !== null ? (float) $address->latitude : null,
            'longitude'    => $address->longitude !== null ? (float) $address->longitude : null,
        ] : null;

        // Clean Ordered Products Items
        if ($this->items && $this->items->isNotEmpty()) {
            $data['items'] = $this->items->map(function ($item) {
                $product = $item->product;
                $variant = $item->variant;
                $primaryImage = $product?->images?->firstWhere('is_primary', true)?->image_url
                    ?? $product?->images?->first()?->image_url;
                $price = (float) $item->price_at_time_of_purchase;
                $quantity = (float) $item->quantity;

                return [
                    'id'          => (int) $item->id,
                    'product_id'  => (int) $item->product_id,
                    'name'        => (string) ($product?->name ?? 'Unknown Product'),
                    'quantity'    => $quantity,
                    'price'       => $price,
                    'total_price' => (float) round($price * $quantity, 2),
                    'image'       => $primaryImage,
                    'variant'     => $variant ? [
                        'id'         => (int) $variant->id,
                        'sku'        => $variant->sku,
                        'attributes' => $variant->attributes,
                    ] : null,
                ];
            })->values();
        } else {
            $data['items'] = [];
        }

        // Assigned Rider (if loaded)
        $data['rider'] = ($this->relationLoaded('rider') && $this->rider) ? [
            'id'            => (int) $this->rider->id,
            'name'          => (string) $this->rider->name,
            'phone_number'  => (string) ($this->rider->phone ?? $this->rider->riderProfile?->phone_number ?? ''),
            'profile_photo' => $this->rider->profile_photo_url,
        ] : null;

        $data['cancellation_reason'] = $this->cancellation_reason ? (string) $this->cancellation_reason : null;
        $data['rating'] = $this->rating !== null ? (int) $this->rating : null;
        $data['review_comment'] = $this->review_comment ? (string) $this->review_comment : null;
        $data['created_at'] = $this->created_at ? Carbon::parse($this->created_at)->toIso8601String() : null;

        return $data;
    }
}
