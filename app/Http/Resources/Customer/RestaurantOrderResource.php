<?php

namespace App\Http\Resources\Customer;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RestaurantOrderResource extends JsonResource
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
            'id'             => (int) $this->id,
            'order_number'   => (string) $this->order_number,
            'status'         => (string) $this->status,
            'payment_method' => (string) $this->payment_method,
            'currency'       => (string) ($merchant?->currency ?? 'KES'),
            'total_amount'   => $totalAmount,
            'delivery_fee'   => $deliveryFee,
            'grand_total'    => $grandTotal,
        ];

        if ($this->distance_km !== null) {
            $data['distance_km'] = (float) $this->distance_km;
        }
        if ($this->duration_minute !== null) {
            $data['duration_minute'] = (int) $this->duration_minute;
        }

        // Clean Restaurant summary
        if ($merchant) {
            $data['restaurant'] = array_filter([
                'id'            => (int) $merchant->id,
                'business_name' => (string) $merchant->business_name,
                'address'       => $merchant->address,
                'phone_number'  => $merchant->phone_number,
                'profile_image' => $merchant->profile_image_url,
            ], fn($val) => !is_null($val));
        }

        // Clean Delivery Address summary
        if ($address) {
            $data['delivery_address'] = array_filter([
                'id'           => (int) $address->id,
                'title'        => (string) $address->title,
                'address_text' => (string) $address->address_text,
                'phone_number' => (string) $address->phone_number,
                'latitude'     => $address->latitude !== null ? (float) $address->latitude : null,
                'longitude'    => $address->longitude !== null ? (float) $address->longitude : null,
            ], fn($val) => !is_null($val));
        }

        // Clean Ordered Food Items
        if ($this->items && $this->items->isNotEmpty()) {
            $data['items'] = $this->items->map(function ($item) {
                $product = $item->product;
                $variant = $item->variant;
                $primaryImage = $product?->images?->firstWhere('is_primary', true)?->image_url
                    ?? $product?->images?->first()?->image_url;
                $price = (float) $item->price_at_time_of_purchase;
                $quantity = (float) $item->quantity;

                $itemData = [
                    'id'          => (int) $item->id,
                    'product_id'  => (int) $item->product_id,
                    'name'        => (string) ($product?->name ?? 'Unknown Food'),
                    'quantity'    => $quantity,
                    'price'       => $price,
                    'total_price' => (float) round($price * $quantity, 2),
                ];

                if ($primaryImage) {
                    $itemData['image'] = $primaryImage;
                }

                if ($variant) {
                    $itemData['variant'] = [
                        'id'         => (int) $variant->id,
                        'attributes' => $variant->attributes,
                    ];
                }

                return $itemData;
            })->values();
        } else {
            $data['items'] = [];
        }

        if ($this->relationLoaded('rider') && $this->rider) {
            $data['rider'] = [
                'id'           => (int) $this->rider->id,
                'name'         => (string) $this->rider->name,
                'phone_number' => (string) $this->rider->phone_number,
            ];
        }

        if ($this->cancellation_reason) {
            $data['cancellation_reason'] = $this->cancellation_reason;
        }

        if ($this->rating !== null) {
            $data['rating'] = (int) $this->rating;
            $data['review_comment'] = $this->review_comment;
        }

        $data['created_at'] = $this->created_at ? Carbon::parse($this->created_at)->toIso8601String() : null;

        return $data;
    }
}
