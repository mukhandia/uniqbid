<?php

namespace Database\Factories;

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\Bidder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuctionBid>
 */
class AuctionBidFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'auction_id' => Auction::factory(),
            'bidder_id' => Bidder::factory(),
            'reference' => 'BID-'.str()->upper(str()->random(10)),
            'amount' => fake()->numberBetween(10, 500),
            'status' => 'pending_payment',
            'paid_at' => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (): array => [
            'status' => 'accepted',
            'paid_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => 'failed',
            'paid_at' => null,
        ]);
    }
}
