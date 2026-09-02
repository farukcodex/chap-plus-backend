<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        $ecommerceCategories = [
            'Fashion' => ['Shirts', 'Accessories', 'Shoes', 'Bags'],
            'Grocery' => ['Fruits', 'Vegetables', 'Spices', 'Ingredients'],
            'Electronics' => ['Phones', 'Laptops', 'MacBook', 'Headphone'],
            'Beauty' => ['Skincare', 'Makeup', 'Hair care', 'Fragrance'],
        ];

        foreach ($ecommerceCategories as $parentName => $subNames) {
            $parent = \App\Models\ProductCategory::updateOrCreate(
                ['name' => $parentName, 'type' => 'ecommerce'],
                ['slug' => \Illuminate\Support\Str::slug($parentName)]
            );

            foreach ($subNames as $subName) {
                \App\Models\ProductCategory::updateOrCreate(
                    ['name' => $subName, 'type' => 'ecommerce', 'parent_id' => $parent->id],
                    ['slug' => \Illuminate\Support\Str::slug($subName)]
                );
            }
        }

        $restaurantCategories = [
            'Burgers' => ['Beef Burgers', 'Chicken Burgers', 'Veggie Burgers'],
            'Pizza' => ['Vegetarian', 'Meat', 'Cheese'],
            'Drinks' => ['Cold Drinks', 'Hot Drinks', 'Smoothies'],
            'Desserts' => ['Cakes', 'Ice Cream', 'Pastries'],
        ];

        foreach ($restaurantCategories as $parentName => $subNames) {
            $parent = \App\Models\ProductCategory::updateOrCreate(
                ['name' => $parentName, 'type' => 'restaurant'],
                ['slug' => \Illuminate\Support\Str::slug($parentName)]
            );

            foreach ($subNames as $subName) {
                \App\Models\ProductCategory::updateOrCreate(
                    ['name' => $subName, 'type' => 'restaurant', 'parent_id' => $parent->id],
                    ['slug' => \Illuminate\Support\Str::slug($subName)]
                );
            }
        }
    }
}
