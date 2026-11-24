<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Subcategory;
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
        $subcategory = Subcategory::factory()->create();

        return [
            'subcategory_id' => $subcategory->id,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'name' => Str::title($name),
            'category' => $subcategory->category->name,
            'subcategory' => $subcategory->name,
            'gender_age' => fake()->randomElement(['Men', 'Women', 'Unisex', 'Kids']),
            'price_tier' => fake()->randomElement(['Budget', 'Affordable', 'Mid-range', 'Premium']),
            'description' => fake()->sentence(),
            'price' => fake()->numberBetween(900, 9000),
            'stock' => fake()->numberBetween(0, 30),
            'reserved_stock' => 0,
            'image' => '/images/products/leather-cardholder.png',
            'is_featured' => false,
            'is_returnable' => true,
            'channel' => Product::CHANNEL_SHOP,
            'auction_sort_order' => 0,
        ];
    }

    public function nonReturnable(): static
    {
        return $this->state(fn (): array => [
            'is_returnable' => false,
        ]);
    }

    public function unassigned(): static
    {
        return $this->state(fn (): array => [
            'channel' => Product::CHANNEL_NONE,
        ]);
    }

    public function inShop(): static
    {
        return $this->state(fn (): array => [
            'channel' => Product::CHANNEL_SHOP,
        ]);
    }

    public function forAuction(): static
    {
        return $this->state(fn (): array => [
            'channel' => Product::CHANNEL_AUCTION,
        ]);
    }

    public function forBoth(): static
    {
        return $this->state(fn (): array => [
            'channel' => Product::CHANNEL_BOTH,
        ]);
    }
}
