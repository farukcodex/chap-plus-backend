<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;
    protected $fillable = [
        'merchant_profile_id', 'category_id', 'name', 'description', 
        'base_price', 'discount_price', 'unit_type', 'unit_value', 
        'has_variants', 'is_active'
    ];

    protected $casts = [
        'has_variants' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function merchantProfile()
    {
        return $this->belongsTo(MerchantProfile::class);
    }

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class);
    }
}
