<?php

namespace Database\Factories;

use App\Models\Bidder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bidder>
 */
class BidderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '2547'.fake()->unique()->numerify('########'),
            'marketing_consent' => false,
            'marketing_consented_at' => null,
        ];
    }

    public function marketingConsent(): static
    {
        return $this->state(fn (): array => [
            'marketing_consent' => true,
            'marketing_consented_at' => now(),
        ]);
    }
}
