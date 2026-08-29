<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Fashion' => ['Shirts', 'Accessories', 'Shoes', 'Bags'],
            'Grocery' => ['Fruits', 'Vegetables', 'Spices', 'Ingredients'],
            'Electronics' => ['Phones', 'Laptops', 'MacBook', 'Headphone'],
            'Beauty' => ['Skincare', 'Makeup', 'Hair care', 'Fragrance'],
        ];

        foreach ($categories as $parentName => $subNames) {
            $parent = \App\Models\ProductCategory::updateOrCreate(
                ['slug' => \Illuminate\Support\Str::slug($parentName)],
                ['name' => $parentName]
            );

            foreach ($subNames as $subName) {
                \App\Models\ProductCategory::updateOrCreate(
                    ['slug' => \Illuminate\Support\Str::slug($subName)],
                    ['name' => $subName, 'parent_id' => $parent->id]
                );
            }
        }
    }
}
