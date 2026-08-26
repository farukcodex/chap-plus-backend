<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Fashion', 'slug' => 'fashion'],
            ['name' => 'Grocery', 'slug' => 'grocery'],
            ['name' => 'Electronics', 'slug' => 'electronics'],
            ['name' => 'Beauty', 'slug' => 'beauty'],
        ];

        foreach ($categories as $category) {
            \App\Models\ProductCategory::updateOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
