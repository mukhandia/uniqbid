<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->sentence(3);

        return [
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'name' => Str::title($name),
            'category' => fake()->randomElement([
                "Men's Accessories",
                "Women's Accessories",
                'Hampers',
                'Gifts',
            ]),
            'description' => fake()->sentence(),
            'price' => fake()->numberBetween(900, 9000),
            'stock' => fake()->numberBetween(0, 30),
            'image' => '/images/products/leather-cardholder.png',
            'is_featured' => false,
        ];
    }
}
