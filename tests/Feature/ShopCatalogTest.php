<?php

use App\Models\Category;
use App\Models\Product;
use App\Services\CartService;
use Livewire\Livewire;

test('shop pages hide the bid now header button', function () {
    $product = Product::factory()->create(['stock' => 5]);

    $this->get(route('shop.index'))
        ->assertOk()
        ->assertDontSee('Bid now');

    $this->get(route('shop.show', $product))
        ->assertOk()
        ->assertDontSee('Bid now');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Bid now');
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

test('shop index filters products by price range', function () {
    Product::factory()->create([
        'name' => 'Budget belt',
        'price' => 1500,
        'stock' => 5,
    ]);
    Product::factory()->create([
        'name' => 'Premium watch',
        'price' => 8000,
        'stock' => 5,
    ]);

    Livewire::test('pages::shop.index')
        ->set('minPrice', '2000')
        ->set('maxPrice', '10000')
        ->assertSee('Premium watch')
        ->assertDontSee('Budget belt')
        ->set('minPrice', '8000')
        ->set('maxPrice', '1000')
        ->assertSee('Budget belt')
        ->assertSee('Premium watch');
});

test('shop index can clear category and price filters', function () {
    Category::factory()->create(['name' => 'Watches', 'slug' => 'watches', 'sort_order' => 1]);

    Product::factory()->create([
        'name' => 'Budget belt',
        'category' => 'Fashion Accessories',
        'price' => 1500,
        'stock' => 5,
    ]);
    Product::factory()->create([
        'name' => 'Premium watch',
        'category' => 'Watches',
        'price' => 8000,
        'stock' => 5,
    ]);

    Livewire::test('pages::shop.index')
        ->set('category', 'Watches')
        ->set('minPrice', '5000')
        ->assertSee('Premium watch')
        ->assertDontSee('Budget belt')
        ->call('clearFilters')
        ->assertSet('category', 'All')
        ->assertSet('minPrice', '')
        ->assertSet('maxPrice', '')
        ->assertSee('Premium watch')
        ->assertSee('Budget belt');
});

test('shop index shows a track an order button and category filters', function () {
    Category::factory()->create(['name' => 'Fashion Accessories', 'slug' => 'fashion-accessories']);

    $this->get(route('shop.index'))
        ->assertOk()
        ->assertSee('Track an order')
        ->assertSee('All products')
        ->assertSee('Price (KES)')
        ->assertSee(route('orders.track'), false);
});

test('shop index does not show a local product search field', function () {
    Product::factory()->create([
        'name' => 'Slim minimalist watch',
        'category' => 'Watches',
        'stock' => 5,
    ]);

    $this->get(route('shop.index'))
        ->assertOk()
        ->assertDontSee('Search products…')
        ->assertSee('Search live sales and shop', false)
        ->assertSee('Slim minimalist watch');
});

test('product quantity can be incremented and decremented without stock caps', function () {
    $product = Product::factory()->create(['stock' => 3]);

    Livewire::test('pages::shop.show', ['product' => $product])
        ->assertSet('qty', 1)
        ->call('increment')
        ->assertSet('qty', 2)
        ->call('increment')
        ->assertSet('qty', 3)
        ->call('increment')
        ->assertSet('qty', 4)
        ->call('decrement')
        ->assertSet('qty', 3)
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

test('shop product page warns when an item is non-returnable', function () {
    $product = Product::factory()->nonReturnable()->create([
        'name' => 'Personalised hamper',
        'stock' => 4,
    ]);

    $this->get(route('shop.show', $product))
        ->assertOk()
        ->assertSee('Personalised hamper')
        ->assertSee('This item is marked non-returnable under our Refund Policy.');
});

test('shop product page does not show a non-returnable notice for returnable items', function () {
    $product = Product::factory()->create([
        'name' => 'Leather cardholder',
        'stock' => 4,
        'is_returnable' => true,
    ]);

    $this->get(route('shop.show', $product))
        ->assertOk()
        ->assertSee('Leather cardholder')
        ->assertDontSee('This item is marked non-returnable under our Refund Policy.');
});

test('out of stock products can still be added to cart without showing stock levels', function () {
    $product = Product::factory()->create([
        'name' => 'Sold Out Item',
        'stock' => 0,
    ]);

    Livewire::test('pages::shop.show', ['product' => $product])
        ->call('addToCart')
        ->assertOk();

    expect(app(CartService::class)->count())->toBe(1);

    $this->get(route('shop.show', $product))
        ->assertOk()
        ->assertDontSee('Out of stock')
        ->assertDontSee('In stock:')
        ->assertDontSee('Only ');

    $this->get(route('shop.index'))
        ->assertOk()
        ->assertDontSee('Out of stock')
        ->assertDontSee('Only ');
});
