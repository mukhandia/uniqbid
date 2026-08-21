<?php

use App\Models\Category;
use App\Models\Product;
use App\Services\CartService;
use Livewire\Livewire;

test('shop pages hide the place your bid header button', function () {
    $product = Product::factory()->create(['stock' => 5]);

    $this->get(route('shop.index'))
        ->assertOk()
        ->assertDontSee('Place Your Bid');

    $this->get(route('shop.show', $product))
        ->assertOk()
        ->assertDontSee('Place Your Bid');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Place Your Bid');
});

test('shop index filters products by category', function () {
    Product::factory()->create([
        'name' => 'Men Belt',
        'category' => 'Fashion Accessories',
        'stock' => 5,
    ]);
    Product::factory()->create([
        'name' => 'Gift Box',
        'category' => 'Gifts & Hampers',
        'stock' => 5,
    ]);

    Category::factory()->create(['name' => 'Fashion Accessories', 'slug' => 'fashion-accessories', 'sort_order' => 1]);
    Category::factory()->create(['name' => 'Gifts & Hampers', 'slug' => 'gifts-hampers', 'sort_order' => 2]);

    Livewire::test('pages::shop.index')
        ->assertSee('Men Belt')
        ->assertSee('Gift Box')
        ->set('category', 'Gifts & Hampers')
        ->assertSee('Gift Box')
        ->assertDontSee('Men Belt');
});

test('shop index can filter categories whose names contain apostrophes', function () {
    Category::factory()->create([
        'name' => "Women's Beauty & Personal",
        'slug' => 'womens-beauty-personal',
        'sort_order' => 1,
    ]);

    Product::factory()->create([
        'name' => 'Makeup brush set',
        'category' => "Women's Beauty & Personal",
        'stock' => 5,
    ]);
    Product::factory()->create([
        'name' => 'Beard grooming kit',
        'category' => "Men's Grooming & Accessories",
        'stock' => 5,
    ]);

    Livewire::test('pages::shop.index')
        ->assertSee("Women's Beauty & Personal")
        ->set('category', "Women's Beauty & Personal")
        ->assertSee('Makeup brush set')
        ->assertDontSee('Beard grooming kit');

    $html = $this->get(route('shop.index'))->assertOk()->getContent();

    expect($html)
        ->toContain('Women\\u0027s Beauty')
        ->not->toContain("wire:click=\"\$set('category', 'Women's Beauty");
});

test('shop index searches products by name', function () {
    Product::factory()->create([
        'name' => 'Slim minimalist watch',
        'category' => 'Watches',
        'stock' => 5,
    ]);
    Product::factory()->create([
        'name' => 'Structured tote handbag',
        'category' => 'Fashion Accessories',
        'stock' => 5,
    ]);

    Livewire::test('pages::shop.index')
        ->set('q', 'minimalist')
        ->assertSee('Slim minimalist watch')
        ->assertDontSee('Structured tote handbag');
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
        ->assertOk()
        ->assertDispatched('cart-updated');

    expect(app(CartService::class)->count())->toBe(2);
    expect(app(CartService::class)->subtotal())->toBe(3600);
});

test('cart count component refreshes when cart-updated is dispatched', function () {
    $product = Product::factory()->create(['stock' => 5, 'price' => 1000]);
    app(CartService::class)->add($product->id, 2);

    Livewire::test('cart-count')
        ->assertSee('2')
        ->call('refreshCount')
        ->assertSee('2');

    app(CartService::class)->add($product->id, 1);

    Livewire::test('cart-count')
        ->dispatch('cart-updated')
        ->assertSee('3');
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
