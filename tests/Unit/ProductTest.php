<?php

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('product scopes featured and in stock', function () {
    Product::factory()->create(['is_featured' => true, 'stock' => 5, 'name' => 'Featured In Stock']);
    Product::factory()->create(['is_featured' => false, 'stock' => 5, 'name' => 'Regular In Stock']);
    Product::factory()->create(['is_featured' => true, 'stock' => 0, 'name' => 'Featured Out']);

    expect(Product::query()->featured()->pluck('name')->all())
        ->toContain('Featured In Stock', 'Featured Out')
        ->not->toContain('Regular In Stock');

    expect(Product::query()->inStock()->pluck('name')->all())
        ->toContain('Featured In Stock', 'Regular In Stock')
        ->not->toContain('Featured Out');
});

test('product low stock helper', function () {
    expect(Product::factory()->make(['stock' => 0])->isLowStock())->toBeFalse()
        ->and(Product::factory()->make(['stock' => 9])->isLowStock())->toBeTrue()
        ->and(Product::factory()->make(['stock' => 10])->isLowStock())->toBeFalse();
});

test('product route key is slug', function () {
    expect((new Product)->getRouteKeyName())->toBe('slug');
});
