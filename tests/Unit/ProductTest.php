<?php

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('product scopes featured and in stock', function () {
    Product::factory()->create(['is_featured' => true, 'stock' => 5, 'reserved_stock' => 0, 'name' => 'Featured In Stock']);
    Product::factory()->create(['is_featured' => false, 'stock' => 5, 'reserved_stock' => 0, 'name' => 'Regular In Stock']);
    Product::factory()->create(['is_featured' => true, 'stock' => 0, 'reserved_stock' => 0, 'name' => 'Featured Out']);
    Product::factory()->create(['is_featured' => false, 'stock' => 2, 'reserved_stock' => 2, 'name' => 'Fully Reserved']);

    expect(Product::query()->featured()->pluck('name')->all())
        ->toContain('Featured In Stock', 'Featured Out')
        ->not->toContain('Regular In Stock');

    expect(Product::query()->inStock()->pluck('name')->all())
        ->toContain('Featured In Stock', 'Regular In Stock')
        ->not->toContain('Featured Out', 'Fully Reserved');
});

test('product low stock helper', function () {
    expect(Product::factory()->make(['stock' => 0])->isLowStock())->toBeFalse()
        ->and(Product::factory()->make(['stock' => 9])->isLowStock())->toBeTrue()
        ->and(Product::factory()->make(['stock' => 10])->isLowStock())->toBeFalse();
});

test('product out of stock and reserved-for-sales scopes', function () {
    Product::factory()->create(['name' => 'Zero stock', 'stock' => 0, 'reserved_stock' => 0]);
    Product::factory()->create(['name' => 'High stock reserved', 'stock' => 100, 'reserved_stock' => 3]);
    Product::factory()->create(['name' => 'Plenty available', 'stock' => 8, 'reserved_stock' => 0]);

    expect(Product::query()->outOfStock()->pluck('name')->all())
        ->toContain('Zero stock')
        ->not->toContain('High stock reserved', 'Plenty available');

    expect(Product::query()->reservedForSales()->pluck('name')->all())
        ->toContain('High stock reserved')
        ->not->toContain('Zero stock', 'Plenty available');
});

test('product route key is slug', function () {
    expect((new Product)->getRouteKeyName())->toBe('slug');
});
