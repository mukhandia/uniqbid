<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $product = Product::factory()->create();
        $qty = fake()->numberBetween(1, 3);

        return [
            'order_id' => Order::factory(),
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'category' => $product->category,
            'unit_price' => $product->price,
            'quantity' => $qty,
            'line_total' => $product->price * $qty,
            'is_returnable' => $product->is_returnable,
        ];
    }
}
