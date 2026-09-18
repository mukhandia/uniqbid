<?php

use App\Models\Auction;
use App\Models\Product;
use Database\Seeders\LaunchAuctionCatalogueSeeder;

test('launch catalogue renames rent package and retires generic phone singles', function () {
    Product::factory()->forAuction()->create([
        'slug' => 'auction-iphone',
        'name' => 'iPhone',
        'stock' => 5,
    ]);
    Product::factory()->forAuction()->create([
        'slug' => 'auction-samsung',
        'name' => 'Samsung',
        'stock' => 5,
    ]);
    Product::factory()->forAuction()->create([
        'slug' => 'auction-rent-deposit',
        'name' => 'Rent + 2 month deposit',
        'stock' => 5,
    ]);
    $legacyRent = Product::factory()->forAuction()->create([
        'slug' => 'rent-2-month-deposit',
        'name' => '2 Month Rent + Deposit',
        'image' => '/images/auctions/macbook-air-m3.png',
        'stock' => 5,
    ]);
    $legacySale = Auction::factory()->onAuctionChannel()->create([
        'product_id' => $legacyRent->id,
        'name' => '2 Month Rent + Deposit',
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);
    $legacySale->images()->create([
        'url' => '/images/auctions/macbook-air-m3.png',
        'label' => '2 Month Rent + Deposit',
        'sort_order' => 0,
    ]);
    Auction::factory()->onAuctionChannel()->create([
        'name' => 'Rent + 2 month deposit',
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    $this->seed(LaunchAuctionCatalogueSeeder::class);

    expect(Product::query()->where('slug', 'auction-rent-deposit')->value('name'))->toBe('2 Month Rent + Deposit')
        ->and(Product::query()->where('slug', 'auction-rent-deposit')->value('image'))->toBe('/images/auctions/rent-keys.png')
        ->and(Auction::query()->where('name', 'Rent + 2 month deposit')->count())->toBe(0)
        ->and(Product::query()->where('slug', 'rent-2-month-deposit')->value('channel'))->toBe(Product::CHANNEL_NONE)
        ->and(Auction::query()->whereKey($legacySale->id)->value('status'))->toBe('voided')
        ->and(Product::query()->where('slug', 'auction-iphone-16')->value('name'))->toBe('iPhone 16')
        ->and(Product::query()->where('slug', 'auction-iphone-17')->value('name'))->toBe('iPhone 17')
        ->and(Product::query()->where('slug', 'auction-samsung-s24')->value('name'))->toBe('Samsung S24')
        ->and(Product::query()->where('slug', 'auction-samsung-s25')->value('name'))->toBe('Samsung S25')
        ->and(Product::query()->where('slug', 'auction-iphone')->value('channel'))->toBe(Product::CHANNEL_NONE)
        ->and(Product::query()->where('slug', 'auction-samsung')->value('channel'))->toBe(Product::CHANNEL_NONE);
});
