<?php

namespace App\Http\Resources\Customer;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EcommerceProductResource extends JsonResource
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

        $merchant = $this->merchantProfile;
        $assignedCategory = $this->category;
        $mainCategory = null;
        $subCategory = null;

        if ($assignedCategory) {
            if ($assignedCategory->parent_id) {
                $root = $assignedCategory->getRootCategory();
                if ($root && $root->id !== $assignedCategory->id) {
                    $mainCategory = $root;
                    $subCategory = $assignedCategory;
                } else {
                    $parent = $assignedCategory->parent ?? $assignedCategory->parent()->first();
                    $mainCategory = $parent ?: $assignedCategory;
                    $subCategory = ($mainCategory->id !== $assignedCategory->id) ? $assignedCategory : null;
                }
            } else {
                $mainCategory = $assignedCategory;
                $subCategory = null;
            }
        }

        // Parse variants into frontend-friendly lists of available colors and sizes
        $availableColors = [];
        $availableSizes = [];

        if ($this->relationLoaded('variants') && $this->variants) {
            foreach ($this->variants as $variant) {
                $attrs = $variant->attributes;
                if (is_array($attrs)) {
                    if (isset($attrs['Color'])) $availableColors[] = $attrs['Color'];
                    if (isset($attrs['color'])) $availableColors[] = $attrs['color'];

                    if (isset($attrs['Size'])) $availableSizes[] = $attrs['Size'];
                    if (isset($attrs['size'])) $availableSizes[] = $attrs['size'];
                }
            }
        }

        $availableColors = array_values(array_unique($availableColors));
        $availableSizes = array_values(array_unique($availableSizes));

        $data = [
            'id'               => (int) $this->id,
            'name'             => (string) $this->name,
            'description'      => $this->description,
            'price'            => (float) $this->base_price,
            'discount_price'   => $this->discount_price !== null ? (float) $this->discount_price : null,
            'currency'         => (string) ($merchant?->currency ?? 'KES'),
            'unit_type'        => $this->unit_type,
            'unit_value'       => $this->unit_value,
            'has_variants'     => (bool) $this->has_variants,
            'rating'           => $this->reviews_avg_rating !== null ? (float) $this->reviews_avg_rating : 0.0,
            'reviews_count'    => (int) ($this->reviews_count ?? 0),
            'is_favorite'      => (bool) ($this->is_favorite ?? false),

            // Images
            'primary_image'    => $primaryImage,
            'images'           => $this->images ? $this->images->map(fn($img) => [
                'id'         => (int) $img->id,
                'image_url'  => $img->image_url,
                'is_primary' => (bool) $img->is_primary,
            ])->values() : [],

            // Category & Sub Category
            'category'         => $mainCategory ? [
                'id'   => (int) $mainCategory->id,
                'name' => (string) $mainCategory->name,
                'slug' => (string) $mainCategory->slug,
            ] : null,
            'sub_category'     => $subCategory ? [
                'id'   => (int) $subCategory->id,
                'name' => (string) $subCategory->name,
                'slug' => (string) $subCategory->slug,
            ] : null,

            // Store summary (public merchant info, no auth leak)
            'store'            => $merchant ? [
                'id'            => (int) $merchant->id,
                'business_name' => (string) $merchant->business_name,
                'phone_number'  => $merchant->phone_number,
                'address'       => $merchant->address,
                'city'          => $merchant->city,
                'country'       => $merchant->country,
                'profile_image' => $merchant->profile_image_url,
            ] : null,

            'available_colors' => $availableColors,
            'available_sizes'  => $availableSizes,
        ];

        // Variants
        if ($this->has_variants && $this->relationLoaded('variants')) {
            $data['variants'] = $this->variants ? $this->variants->map(fn($v) => [
                'id'               => (int) $v->id,
                'sku'              => $v->sku,
                'attributes'       => $v->attributes,
                'price_adjustment' => (float) ($v->price_adjustment ?? 0),
                'stock_quantity'   => (int) ($v->stock_quantity ?? 0),
            ])->values() : [];
        } else {
            $data['variants'] = [];
        }

        // Reviews (if loaded, for show product screen)
        if ($this->relationLoaded('reviews')) {
            $data['reviews'] = $this->reviews ? $this->reviews->map(fn($rev) => [
                'id'         => (int) $rev->id,
                'rating'     => (int) $rev->rating,
                'comment'    => $rev->comment,
                'reviewer'   => $rev->user ? [
                    'id'     => (int) $rev->user->id,
                    'name'   => (string) $rev->user->name,
                    'avatar' => $rev->user->profile_photo_url,
                ] : null,
                'created_at' => $rev->created_at ? Carbon::parse($rev->created_at)->toIso8601String() : null,
            ])->values() : [];
        }

        return $data;
    }
}
