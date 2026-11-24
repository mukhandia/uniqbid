<?php

namespace Database\Factories;

use App\Models\SupportTicket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => null,
            'subject' => fake()->sentence(4),
            'message' => fake()->paragraph(),
            'status' => SupportTicket::STATUS_OPEN,
            'read_at' => null,
        ];
    }

    public function read(): static
    {
        return $this->state(fn (): array => [
            'read_at' => now(),
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (): array => [
            'status' => SupportTicket::STATUS_IN_PROGRESS,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (): array => [
            'status' => SupportTicket::STATUS_CLOSED,
            'read_at' => now(),
        ]);
    }
}
