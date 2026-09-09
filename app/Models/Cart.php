<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $fillable = ['user_id', 'type'];

    public function scopeEcommerce($query)
    {
        return $query->where('type', 'ecommerce');
    }

    public function scopeRestaurant($query)
    {
        return $query->where('type', 'restaurant');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }
}
