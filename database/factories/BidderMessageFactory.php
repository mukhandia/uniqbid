<?php

namespace Database\Factories;

use App\Models\Auction;
use App\Models\Bidder;
use App\Models\BidderMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BidderMessage>
 */
class BidderMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'auction_id' => Auction::factory(),
            'bidder_id' => Bidder::factory(),
            'sent_by' => null,
            'phone' => fn (array $attributes): string => Bidder::query()->whereKey($attributes['bidder_id'])->value('phone') ?? '254712345678',
            'body' => fake()->sentence(),
            'status' => BidderMessage::STATUS_PENDING,
        ];
    }

    public function delivered(): static
    {
        return $this->state(fn (): array => [
            'status' => BidderMessage::STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => BidderMessage::STATUS_FAILED,
            'failure_reason' => 'Delivery failed.',
        ]);
    }
}
