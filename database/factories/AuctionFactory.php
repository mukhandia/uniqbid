<?php

namespace Database\Factories;

use App\Models\Auction;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Auction>
 */
class AuctionFactory extends Factory
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
            'description' => fake()->paragraph(),
            'bid_fee' => fake()->randomElement([20, 30, 40, 50, 60, 100]),
            'min_bid' => 1,
            'max_bid' => fake()->randomElement([4000, 8000, 10000, 15000, 25000]),
            'bid_increment' => 1,
            'retail_value' => fake()->numberBetween(20000, 200000),
            'bids_count' => fake()->numberBetween(50, 900),
            'ends_at' => now()->addHours(fake()->numberBetween(1, 48)),
            'status' => 'live',
            'is_featured' => false,
            'similar_sales' => [1200, 1800, 2400],
            'current_round' => 1,
            'duration_hours' => 24,
            'display_order' => fake()->numberBetween(10, 1000),
            'extension_minutes' => 60,
            'no_unique_bid_rule' => 'extend',
        ];
    }

    /**
     * Live sale linked to an auction-channel product (public Live Auctions catalogue).
     */
    public function onAuctionChannel(): static
    {
        return $this->state(fn (): array => [
            'product_id' => Product::factory()->forAuction(),
        ]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Auction $auction): void {
            $auction->images()->create([
                'url' => '/images/auctions/iphone-15-pro.png',
                'label' => $auction->name,
                'sort_order' => 0,
            ]);
        });
    }
}
