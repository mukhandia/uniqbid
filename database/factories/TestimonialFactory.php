<?php

namespace Database\Factories;

use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Testimonial>
 */
class TestimonialFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'location' => fake()->city(),
            'role' => fake()->randomElement(['Successful buyer', 'Regular bidder', 'Shop customer']),
            'item' => fake()->words(3, true),
            'quote' => fake()->paragraph(),
            'rating' => fake()->numberBetween(4, 5),
            'sort_order' => fake()->numberBetween(1, 20),
            'is_published' => true,
        ];
    }
}
