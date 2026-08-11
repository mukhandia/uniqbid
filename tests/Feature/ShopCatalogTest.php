<?php

use App\Models\Product;
use App\Services\CartService;
use Livewire\Livewire;

test('shop index filters products by category', function () {
    Product::factory()->create([
        'name' => 'Men Belt',
        'category' => "Men's Accessories",
        'stock' => 5,
    ]);
    Product::factory()->create([
        'name' => 'Gift Box',
        'category' => 'Gifts',
        'stock' => 5,
    ]);

    Livewire::test('pages::shop.index')
        ->assertSee('Men Belt')
        ->assertSee('Gift Box')
        ->set('category', 'Gifts')
        ->assertSee('Gift Box')
        ->assertDontSee('Men Belt');
});

test('product quantity can be incremented and decremented within stock', function () {
    $product = Product::factory()->create(['stock' => 3]);

    Livewire::test('pages::shop.show', ['product' => $product])
        ->assertSet('qty', 1)
        ->call('increment')
        ->assertSet('qty', 2)
        ->call('increment')
        ->assertSet('qty', 3)
        ->call('increment')
        ->assertSet('qty', 3)
        ->call('decrement')
        ->assertSet('qty', 2)
        ->call('decrement')
        ->call('decrement')
        ->assertSet('qty', 1)
        ->call('decrement')
        ->assertSet('qty', 1);
});

test('add to cart adds the selected quantity', function () {
    $product = Product::factory()->create([
        'name' => 'Leather Cardholder',
        'stock' => 10,
        'price' => 1800,
    ]);

    Livewire::test('pages::shop.show', ['product' => $product])
        ->call('increment')
        ->call('addToCart')
        ->assertOk();

    expect(app(CartService::class)->count())->toBe(2);
    expect(app(CartService::class)->subtotal())->toBe(3600);
});

test('out of stock products cannot be added to cart', function () {
    $product = Product::factory()->create([
        'name' => 'Sold Out Item',
        'stock' => 0,
    ]);

    Livewire::test('pages::shop.show', ['product' => $product])
        ->call('addToCart')
        ->assertOk();

    expect(app(CartService::class)->count())->toBe(0);

    $this->get(route('shop.show', $product))
        ->assertOk()
        ->assertSee('Out of stock');
});
