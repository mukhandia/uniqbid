<?php

namespace Database\Factories;

use App\Models\AuctionBid;
use App\Models\PaymentAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentAttempt>
 */
class PaymentAttemptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'auction_bid_id' => AuctionBid::factory(),
            'provider' => 'mpesa_daraja_stk_push',
            'reference' => 'PAY-'.str()->upper(str()->random(10)),
            'amount' => fake()->randomElement([20, 30, 40, 50]),
            'status' => 'initiated',
            'failure_reason' => null,
            'request_payload' => [],
            'response_payload' => null,
        ];
    }

    public function succeeded(): static
    {
        return $this->state(fn (): array => [
            'status' => 'succeeded',
            'response_payload' => ['status' => 'stk_push_simulated_success'],
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => 'failed',
            'failure_reason' => 'Payment was not completed.',
            'response_payload' => ['status' => 'stk_push_simulated_failure'],
        ]);
    }
}
