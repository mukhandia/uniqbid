<?php

namespace Database\Factories;

use App\Models\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faq>
 */
class FaqFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category' => Faq::CATEGORY_GENERAL,
            'question_en' => fake()->sentence().'?',
            'answer_en' => fake()->paragraph(),
            'question_sw' => null,
            'answer_sw' => null,
            'sort_order' => fake()->numberBetween(1, 20),
            'is_published' => true,
        ];
    }
}
