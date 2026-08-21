<?php

use App\Models\Auction;
use App\Models\Product;
use App\Services\CreateSaleFromProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('creating a sale from a product reserves stock and links the auction', function () {
    $product = Product::factory()->create([
        'name' => 'Rose-gold bracelet watch',
        'price' => 4950,
        'stock' => 5,
        'reserved_stock' => 0,
        'category' => 'Watches',
        'image' => '/images/products/wooden-watch.png',
    ]);

    $auction = app(CreateSaleFromProductService::class)->handle($product, [
        'bid_fee' => 40,
        'min_bid' => 1,
        'max_bid' => 2500,
        'ends_in_hours' => 12,
        'reserve_stock' => true,
    ]);

    expect($auction->product_id)->toBe($product->id)
        ->and($auction->name)->toBe($product->name)
        ->and($auction->retail_value)->toBe(4950)
        ->and($auction->bid_fee)->toBe(40)
        ->and($auction->status)->toBe('live')
        ->and($auction->images)->toHaveCount(1)
        ->and($product->fresh()->reserved_stock)->toBe(1)
        ->and($product->fresh()->availableStock())->toBe(4);
});

test('creating a sale fails when no available stock remains', function () {
    $product = Product::factory()->create([
        'stock' => 1,
        'reserved_stock' => 1,
    ]);

    expect(fn () => app(CreateSaleFromProductService::class)->handle($product, [
        'bid_fee' => 30,
        'reserve_stock' => true,
    ]))->toThrow(ValidationException::class);
});

test('voiding releases reserved stock', function () {
    $product = Product::factory()->create(['stock' => 3, 'reserved_stock' => 0]);
    $service = app(CreateSaleFromProductService::class);

    $auction = $service->handle($product, [
        'bid_fee' => 25,
        'reserve_stock' => true,
    ]);

    expect($product->fresh()->reserved_stock)->toBe(1);

    $service->releaseReservedStock($auction);

    expect($product->fresh()->reserved_stock)->toBe(0)
        ->and(Auction::query()->whereKey($auction->id)->exists())->toBeTrue();
});
