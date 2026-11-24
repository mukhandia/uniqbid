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
        ->and($auction->is_featured)->toBeFalse()
        ->and($auction->images)->toHaveCount(1)
        ->and($product->fresh()->reserved_stock)->toBe(1)
        ->and($product->fresh()->availableStock())->toBe(4)
        ->and($product->fresh()->channel)->toBe(Product::CHANNEL_BOTH)
        ->and($product->fresh()->isInShop())->toBeTrue()
        ->and($product->fresh()->isInAuctionChannel())->toBeTrue();
});

test('sale pricing defaults to zero point one percent fee and ten percent max bid', function () {
    $product = Product::factory()->create(['price' => 100_000]);

    expect(CreateSaleFromProductService::pricingFromProduct($product))->toBe([
        'bid_fee' => 100,
        'min_bid' => 1,
        'max_bid' => 10_000,
    ]);

    $auction = app(CreateSaleFromProductService::class)->handle($product, [
        'ends_in_hours' => 24,
        'reserve_stock' => false,
    ]);

    expect($auction->bid_fee)->toBe(100)
        ->and($auction->min_bid)->toBe(1)
        ->and($auction->max_bid)->toBe(10_000);
});

test('creating a featured sale from a product makes it the only homepage hero', function () {
    $previous = Auction::factory()->create(['is_featured' => true]);
    $product = Product::factory()->create([
        'name' => 'Homepage hero watch',
        'price' => 4950,
        'stock' => 3,
        'reserved_stock' => 0,
    ]);

    $auction = app(CreateSaleFromProductService::class)->handle($product, [
        'bid_fee' => 40,
        'is_featured' => true,
    ]);

    expect($auction->fresh()->is_featured)->toBeTrue()
        ->and($previous->fresh()->is_featured)->toBeFalse()
        ->and(Auction::query()->featured()->count())->toBe(1);
});

test('creating a sale is allowed when no available stock remains', function () {
    $product = Product::factory()->create([
        'stock' => 1,
        'reserved_stock' => 1,
    ]);

    $auction = app(CreateSaleFromProductService::class)->handle($product, [
        'bid_fee' => 30,
        'reserve_stock' => true,
    ]);

    expect($auction->product_id)->toBe($product->id)
        ->and($product->fresh()->reserved_stock)->toBe(2)
        ->and($product->fresh()->stock)->toBe(1);
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

test('creating a second live sale for the same product is blocked', function () {
    $product = Product::factory()->create(['stock' => 5, 'reserved_stock' => 0]);
    $service = app(CreateSaleFromProductService::class);

    $service->handle($product, ['bid_fee' => 30]);

    try {
        $service->handle($product->fresh(), ['bid_fee' => 30]);
        $this->fail('A second live sale should not be created.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['product'][0])->toContain('already has a live sale')
            ->and(Auction::query()->where('product_id', $product->id)->count())->toBe(1);
    }
});

test('creating a sale is blocked until an ended sale is settled', function () {
    $product = Product::factory()->create(['stock' => 5, 'reserved_stock' => 0]);
    Auction::factory()->create([
        'product_id' => $product->id,
        'status' => 'live',
        'ends_at' => now()->subMinute(),
        'settled_at' => null,
    ]);

    try {
        app(CreateSaleFromProductService::class)->handle($product, ['bid_fee' => 30]);
        $this->fail('A new sale should not be created before settlement.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['product'][0])->toContain('Settle the ended sale')
            ->and(Auction::query()->where('product_id', $product->id)->count())->toBe(1);
    }
});

test('creating a sale is allowed after the previous sale is settled or voided', function () {
    $product = Product::factory()->create(['stock' => 5, 'reserved_stock' => 0]);
    $service = app(CreateSaleFromProductService::class);

    Auction::factory()->create([
        'product_id' => $product->id,
        'status' => 'closed',
        'ends_at' => now()->subDay(),
        'settled_at' => now()->subDay(),
    ]);

    $next = $service->handle($product, ['bid_fee' => 30]);

    expect($next->product_id)->toBe($product->id)
        ->and($next->status)->toBe('live');

    $next->update(['status' => 'voided', 'settled_at' => now()]);
    $service->releaseReservedStock($next);

    $afterVoid = $service->handle($product->fresh(), ['bid_fee' => 25]);

    expect($afterVoid->id)->not->toBe($next->id)
        ->and($afterVoid->status)->toBe('live');
});
