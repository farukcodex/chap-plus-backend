<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Pick a random SUBCATEGORY (where parent_id is not null)
        $category = \App\Models\ProductCategory::whereNotNull('parent_id')->inRandomOrder()->first();
        
        $names = [
            'fashion' => ['Cotton T-Shirt', 'Denim Jeans', 'Leather Jacket', 'Sneakers', 'Sunglasses', 'Winter Coat'],
            'grocery' => ['Fresh Banana', 'Red Apple', 'Organic Milk', 'Whole Wheat Bread', 'Rice 5kg', 'Tomatoes'],
            'electronics' => ['Wireless Mouse', 'Bluetooth Headphones', 'Smartphone', '4K TV', 'Power Bank', 'Laptop'],
            'beauty' => ['Matte Lipstick', 'Face Serum', 'Moisturizing Cream', 'Perfume', 'Nail Polish', 'Sunscreen'],
        ];
        
        $descriptions = [
            'fashion' => ['High quality material, perfect for any occasion.', 'Stylish and comfortable everyday wear.', 'Trendy design that stands out from the crowd.'],
            'grocery' => ['Freshly sourced from local organic farms.', 'Rich in vitamins and perfectly ripe.', 'Premium quality pantry essential for your kitchen.'],
            'electronics' => ['Latest technology with a 1-year manufacturer warranty.', 'High performance and exceptional battery life.', 'Sleek design with blazing fast processing speed.'],
            'beauty' => ['Dermatologist tested and cruelty-free formulation.', 'Long-lasting glow for radiant and healthy skin.', 'Enriched with essential oils and natural extracts.'],
        ];

        // Determine main category slug from parent
        $slug = $category && $category->parent ? $category->parent->slug : 'grocery';
        $realName = fake()->randomElement($names[$slug] ?? $names['grocery']);
        $realDesc = fake()->randomElement($descriptions[$slug] ?? $descriptions['grocery']);
        
        // Adjust unit type logically based on category
        $unitType = 'pcs';
        $unitValue = 1;
        if ($slug === 'grocery') {
            $unitType = fake()->randomElement(['kg', 'g', 'liters', 'pcs']);
            $unitValue = fake()->randomElement([1, 500, 2]);
        }

        return [
            'merchant_profile_id' => 1,
            'category_id' => $category->id ?? 1,
            'name' => $realName,
            'description' => $realDesc,
            'base_price' => fake()->randomFloat(2, 5, 200),
            'discount_price' => null,
            'unit_type' => $unitType,
            'unit_value' => $unitValue,
            'has_variants' => false,
            'is_active' => true,
        ];
    }
}
