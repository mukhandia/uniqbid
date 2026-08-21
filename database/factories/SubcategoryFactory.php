<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subcategory>
 */
class SubcategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'category_id' => Category::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(10, 99),
            'sort_order' => fake()->numberBetween(1, 50),
            'is_active' => true,
        ];
    }
}
