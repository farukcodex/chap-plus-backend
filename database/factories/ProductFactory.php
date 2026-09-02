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
        // Pick a random merchant
        $merchant = \App\Models\MerchantProfile::inRandomOrder()->first();
        $isRestaurant = $merchant && $merchant->user->hasRole('RESTAURANT_MERCHANT');
        $type = $isRestaurant ? 'restaurant' : 'ecommerce';

        // Pick a random SUBCATEGORY matching the type
        $category = \App\Models\ProductCategory::where('type', $type)
            ->whereNotNull('parent_id')
            ->inRandomOrder()->first();
        
        $names = [
            'fashion' => ['Cotton T-Shirt', 'Denim Jeans', 'Leather Jacket', 'Sneakers', 'Sunglasses', 'Winter Coat'],
            'grocery' => ['Fresh Banana', 'Red Apple', 'Organic Milk', 'Whole Wheat Bread', 'Rice 5kg', 'Tomatoes'],
            'electronics' => ['Wireless Mouse', 'Bluetooth Headphones', 'Smartphone', '4K TV', 'Power Bank', 'Laptop'],
            'beauty' => ['Matte Lipstick', 'Face Serum', 'Moisturizing Cream', 'Perfume', 'Nail Polish', 'Sunscreen'],
            // Restaurant categories (slugs)
            'burgers' => ['Classic Cheeseburger', 'Double Beef Burger', 'Spicy Chicken Burger', 'Veggie Delight Burger'],
            'pizza' => ['Margherita Pizza', 'Pepperoni Pizza', 'BBQ Chicken Pizza', 'Hawaiian Pizza'],
            'drinks' => ['Coca Cola', 'Fresh Orange Juice', 'Mango Smoothie', 'Iced Latte'],
            'desserts' => ['Chocolate Lava Cake', 'Vanilla Ice Cream', 'Strawberry Cheesecake', 'Brownie'],
        ];
        
        $descriptions = [
            'ecommerce' => [
                'High quality material, perfect for any occasion.', 
                'Stylish and comfortable everyday wear.', 
                'Freshly sourced from local organic farms.', 
                'Premium quality essential.',
                'Latest technology with a 1-year manufacturer warranty.'
            ],
            'restaurant' => [
                'Freshly prepared with our secret signature sauce.',
                'Served hot and fresh out of the oven.',
                'A delightful treat to satisfy your cravings.',
                'Made with premium ingredients and authentic spices.'
            ],
        ];

        // Determine main category slug from parent
        $slug = $category && $category->parent ? $category->parent->slug : ($isRestaurant ? 'burgers' : 'grocery');
        $realName = fake()->randomElement($names[$slug] ?? $names[$isRestaurant ? 'burgers' : 'grocery']);
        $realDesc = fake()->randomElement($descriptions[$type] ?? $descriptions['ecommerce']);
        
        // Adjust unit type logically based on category
        $unitType = 'pcs';
        $unitValue = 1;
        if ($slug === 'grocery') {
            $unitType = fake()->randomElement(['kg', 'g', 'liters', 'pcs']);
            $unitValue = fake()->randomElement([1, 500, 2]);
        } elseif ($isRestaurant) {
            $unitType = fake()->randomElement(['portion', 'plate', 'glass']);
            $unitValue = 1;
        }

        return [
            'merchant_profile_id' => $merchant->id ?? 1,
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
