<?php

use App\Models\Product;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('cart service adds updates removes and clears items', function () {
    $first = Product::factory()->create(['price' => 1000, 'stock' => 10]);
    $second = Product::factory()->create(['price' => 500, 'stock' => 10]);
    $cart = app(CartService::class);

    $cart->add($first->id, 2);
    $cart->add($first->id, 1);
    $cart->add($second->id, 1);

    expect($cart->count())->toBe(4)
        ->and($cart->subtotal())->toBe(3500);

    $cart->update($first->id, 1);
    expect($cart->count())->toBe(2)
        ->and($cart->subtotal())->toBe(1500);

    $cart->remove($second->id);
    expect($cart->count())->toBe(1)
        ->and($cart->lines())->toHaveCount(1);

    $cart->clear();
    expect($cart->items())->toBe([])
        ->and($cart->count())->toBe(0)
        ->and($cart->subtotal())->toBe(0);
});

test('cart service ignores missing products when building lines', function () {
    $product = Product::factory()->create(['price' => 800, 'stock' => 3]);
    $cart = app(CartService::class);

    $cart->add($product->id, 1);
    $product->delete();

    expect($cart->lines())->toHaveCount(0)
        ->and($cart->subtotal())->toBe(0);
});
