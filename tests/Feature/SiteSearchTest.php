<?php

use App\Models\Auction;
use App\Models\Product;
use App\Services\SiteSearchService;
use Livewire\Livewire;

test('home and shop pages show a search bar under the header', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Search live sales and shop', false)
        ->assertDontSee('@if');

    $this->get(route('shop.index'))
        ->assertOk()
        ->assertSee('Search live sales and shop', false)
        ->assertDontSee('@if');
});

test('searching finds a live auction', function () {
    $headphones = Product::factory()->forAuction()->create([
        'name' => 'Sony WH-1000XM5 Headphones',
        'category' => 'Electronics',
    ]);
    Auction::factory()->create([
        'product_id' => $headphones->id,
        'name' => $headphones->name,
        'category' => $headphones->category,
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    $blender = Product::factory()->forAuction()->create([
        'name' => 'Kitchen blender bundle',
        'category' => 'Home',
    ]);
    Auction::factory()->create([
        'product_id' => $blender->id,
        'name' => $blender->name,
        'category' => $blender->category,
        'status' => 'live',
        'ends_at' => now()->addDays(2),
    ]);

    Livewire::test('site-search')
        ->set('query', 'headphones')
        ->assertSee('Sony WH-1000XM5 Headphones')
        ->assertSee('Live auction')
        ->assertDontSee('Kitchen blender bundle');
});

test('searching finds a shop product', function () {
    Product::factory()->create([
        'name' => 'Slim minimalist watch',
        'category' => 'Watches',
        'stock' => 4,
    ]);
    Product::factory()->create([
        'name' => 'Leather cardholder',
        'category' => 'Fashion Accessories',
        'stock' => 8,
    ]);

    Livewire::test('site-search')
        ->set('query', 'minimalist')
        ->assertSee('Slim minimalist watch')
        ->assertSee('Shop')
        ->assertDontSee('Leather cardholder');
});

test('an item linked to a live sale and the shop is marked as found in both', function () {
    $product = Product::factory()->forBoth()->create([
        'name' => 'Dyson Airwrap',
        'category' => 'Beauty',
        'stock' => 3,
    ]);
    Auction::factory()->create([
        'product_id' => $product->id,
        'name' => $product->name,
        'category' => $product->category,
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    Livewire::test('site-search')
        ->set('query', 'Airwrap')
        ->assertSee('Dyson Airwrap')
        ->assertSee('Found in live auction and shop')
        ->assertSee('View sale')
        ->assertSee('View in shop')
        ->assertDontSee('>Live auction<', false);
});

test('the same item name in a live sale and the shop is marked as found in both', function () {
    $product = Product::factory()->forBoth()->create([
        'name' => 'MacBook Air M3',
        'category' => 'Electronics',
        'stock' => 2,
    ]);
    Auction::factory()->create([
        'product_id' => $product->id,
        'name' => 'MacBook Air M3',
        'category' => 'Electronics',
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    $results = app(SiteSearchService::class)->search('MacBook');

    expect($results)->toHaveCount(1)
        ->and($results->first()['in_auction'])->toBeTrue()
        ->and($results->first()['in_shop'])->toBeTrue()
        ->and($results->first()['name'])->toBe('MacBook Air M3');
});

test('closed and ended auctions are excluded from search', function () {
    Auction::factory()->onAuctionChannel()->create([
        'name' => 'Closed iPhone sale',
        'status' => 'closed',
        'ends_at' => now()->subHour(),
    ]);
    Auction::factory()->onAuctionChannel()->create([
        'name' => 'Expired iPhone listing',
        'status' => 'live',
        'ends_at' => now()->subMinute(),
    ]);
    Product::factory()->create([
        'name' => 'iPhone case pack',
        'stock' => 5,
    ]);

    Livewire::test('site-search')
        ->set('query', 'iPhone')
        ->assertSee('iPhone case pack')
        ->assertSee('Shop')
        ->assertDontSee('Closed iPhone sale')
        ->assertDontSee('Expired iPhone listing');
});

test('short queries do not open the results dropdown', function () {
    Product::factory()->create(['name' => 'Gift hamper', 'stock' => 2]);

    Livewire::test('site-search')
        ->set('query', 'G')
        ->assertDontSee('Gift hamper')
        ->assertDontSee('No live sales or shop items match')
        ->assertSee('aria-expanded="false"', false);
});
