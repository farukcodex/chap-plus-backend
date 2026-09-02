<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductCategory extends Model
{
    use HasFactory;

    protected $fillable = ['type', 'merchant_profile_id', 'name', 'slug', 'parent_id'];

    public function merchantProfile()
    {
        return $this->belongsTo(MerchantProfile::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    public function parent()
    {
        return $this->belongsTo(ProductCategory::class, 'parent_id');
    }

    public function subcategories()
    {
        return $this->hasMany(ProductCategory::class, 'parent_id');
    }
}
