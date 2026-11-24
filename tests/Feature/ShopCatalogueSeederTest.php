<?php

use App\Models\AuctionResult;
use App\Models\Category;
use App\Models\Product;
use Database\Seeders\ShopCatalogueSeeder;

test('shop catalogue seeder creates the launch catalogue without demo past sales', function () {
    $this->seed(ShopCatalogueSeeder::class);

    $catalogue = json_decode((string) file_get_contents(database_path('data/catalogue_launch.json')), true);

    expect(Category::query()->count())->toBe(count($catalogue['categories']))
        ->and(Product::query()->where('channel', Product::CHANNEL_SHOP)->count())->toBe(count($catalogue['products']))
        ->and(AuctionResult::query()->count())->toBe(0);
});

test('shop catalogue seeder never overwrites existing products or categories', function () {
    $this->seed(ShopCatalogueSeeder::class);

    $product = Product::query()->where('channel', Product::CHANNEL_SHOP)->firstOrFail();
    $product->update(['name' => 'Admin renamed', 'image' => '/storage/products/custom.png']);

    $category = Category::query()->firstOrFail();
    $category->update(['name' => 'Admin category', 'is_active' => false]);

    $this->seed(ShopCatalogueSeeder::class);

    expect($product->fresh()->name)->toBe('Admin renamed')
        ->and($product->fresh()->image)->toBe('/storage/products/custom.png')
        ->and($category->fresh()->name)->toBe('Admin category')
        ->and($category->fresh()->is_active)->toBeFalse();
});
