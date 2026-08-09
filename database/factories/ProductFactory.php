<?php

namespace Database\Factories;

use App\Models\Product;
use App\Support\PlaceholderImage;
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
        $name = fake()->unique()->words(3, true);

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
            'image' => PlaceholderImage::make(Str::title($name), '#132a52'),
            'is_featured' => false,
        ];
    }
}
