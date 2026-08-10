<?php

namespace Database\Factories;

use App\Models\AuctionResult;
use App\Support\PlaceholderImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AuctionResult>
 */
class AuctionResultFactory extends Factory
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
            'category' => fake()->randomElement(['Electronics', 'Vehicles', 'Home']),
            'image' => PlaceholderImage::make(Str::title($name), '#0f2a4a'),
            'sale_price' => fake()->numberBetween(500, 9000),
            'buyer_display' => fake()->firstName().' '.strtoupper(fake()->randomLetter()).'.',
            'sold_on' => fake()->dateTimeBetween('-60 days', '-1 day'),
        ];
    }
}
