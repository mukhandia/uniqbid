<?php

use App\Models\Product;
use App\Services\CartService;
use Livewire\Livewire;

test('cart page shows empty state', function () {
    $this->get(route('cart'))
        ->assertOk()
        ->assertSee('Your cart is empty');
});

test('cart page lists items and can update and remove quantities', function () {
    $product = Product::factory()->create([
        'name' => 'Leather Cardholder',
        'price' => 1500,
        'stock' => 10,
    ]);

    app(CartService::class)->add($product->id, 2);

    Livewire::test('pages::cart')
        ->assertSee('Leather Cardholder')
        ->assertSee('Your cart')
        ->assertSee('Order summary')
        ->assertSee('Delivery charges apply outside Nairobi')
        ->assertSee('Charges apply')
        ->assertDontSee('KES 350')
        ->call('increment', $product->id)
        ->assertOk()
        ->assertDispatched('cart-updated');

    expect(app(CartService::class)->count())->toBe(3);

    Livewire::test('pages::cart')
        ->call('decrement', $product->id)
        ->assertOk();

    expect(app(CartService::class)->count())->toBe(2);

    Livewire::test('pages::cart')
        ->call('remove', $product->id)
        ->assertOk();

    expect(app(CartService::class)->count())->toBe(0);
});

test('updating quantity below one removes the cart line', function () {
    $product = Product::factory()->create(['stock' => 5]);

    app(CartService::class)->add($product->id, 1);

    Livewire::test('pages::cart')
        ->call('decrement', $product->id);

    expect(app(CartService::class)->items())->toBe([]);
});

test('cart increment cannot exceed available stock', function () {
    $product = Product::factory()->create(['stock' => 2, 'reserved_stock' => 0]);

    app(CartService::class)->add($product->id, 2);

    Livewire::test('pages::cart')
        ->call('increment', $product->id)
        ->assertOk();

    expect(app(CartService::class)->quantityOf($product->id))->toBe(2);
});
