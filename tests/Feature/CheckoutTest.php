<?php

use App\Models\Product;
use App\Services\CartService;
use Livewire\Livewire;

test('checkout redirects to cart when empty', function () {
    $this->get(route('checkout'))
        ->assertRedirect(route('cart'));
});

test('checkout page renders when cart has items', function () {
    $product = Product::factory()->create([
        'name' => 'Gift Hamper',
        'price' => 2500,
        'stock' => 5,
    ]);

    app(CartService::class)->add($product->id, 1);

    $this->get(route('checkout'))
        ->assertOk()
        ->assertSee('Checkout')
        ->assertSee('Gift Hamper');
});

test('checkout validates required fields', function () {
    $product = Product::factory()->create(['stock' => 5]);
    app(CartService::class)->add($product->id, 1);

    Livewire::test('pages::checkout')
        ->set('name', '')
        ->set('phone', '123')
        ->set('delivery', 'nairobi')
        ->set('address', '')
        ->call('placeOrder')
        ->assertHasErrors(['name', 'phone', 'address']);
});

test('checkout allows collection without address', function () {
    $product = Product::factory()->create(['stock' => 5, 'price' => 1000]);
    app(CartService::class)->add($product->id, 2);

    Livewire::test('pages::checkout')
        ->set('name', 'Amina Wanjiku')
        ->set('phone', '0712345678')
        ->set('delivery', 'collection')
        ->set('address', '')
        ->call('placeOrder')
        ->assertHasNoErrors()
        ->assertSet('status', 'success')
        ->assertSet('orderRef', fn ($ref) => str_starts_with((string) $ref, 'ORD'));

    expect(app(CartService::class)->count())->toBe(0);
});

test('checkout places order and clears the cart', function () {
    $product = Product::factory()->create(['stock' => 5, 'price' => 1200]);
    app(CartService::class)->add($product->id, 1);

    Livewire::test('pages::checkout')
        ->set('name', 'James Mwangi')
        ->set('phone', '0798765432')
        ->set('delivery', 'nairobi')
        ->set('address', 'Westlands, Nairobi')
        ->call('placeOrder')
        ->assertHasNoErrors()
        ->assertSet('status', 'success')
        ->assertSee('Order received');

    expect(app(CartService::class)->count())->toBe(0);
});
