<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(1500, 12000);

        return [
            'reference' => 'ORD'.fake()->unique()->numerify('######'),
            'user_id' => null,
            'customer_name' => fake()->name(),
            'customer_phone' => '2547'.fake()->numerify('########'),
            'customer_email' => fake()->optional()->safeEmail(),
            'delivery_method' => fake()->randomElement([
                Order::DELIVERY_NAIROBI,
                Order::DELIVERY_COLLECTION,
                Order::DELIVERY_UPCOUNTRY,
            ]),
            'delivery_address' => fake()->address(),
            'subtotal' => $subtotal,
            'delivery_fee' => 0,
            'total' => $subtotal,
            'status' => Order::STATUS_PAID,
            'payment_provider' => 'mpesa_demo',
            'payment_reference' => 'MPESA'.fake()->numerify('######'),
            'paid_at' => now(),
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user->id,
            'customer_name' => $user->name,
            'customer_phone' => $user->phone ?? '254712345678',
            'customer_email' => $user->email,
        ]);
    }

    public function dispatched(): static
    {
        return $this->state(fn (): array => [
            'status' => Order::STATUS_DISPATCHED,
            'dispatched_at' => now(),
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (): array => [
            'status' => Order::STATUS_PROCESSING,
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (): array => [
            'status' => Order::STATUS_DELIVERED,
            'dispatched_at' => now()->subDay(),
            'delivered_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => Order::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancel_reason' => 'Customer cancelled before dispatch',
        ]);
    }
}
