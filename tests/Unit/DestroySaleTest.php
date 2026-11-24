<?php

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionImage;
use App\Models\AuctionResult;
use App\Models\Bidder;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Services\CreateSaleFromProductService;
use App\Services\DestroySaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('deleting a sale returns reserved stock and removes attached records', function () {
    $product = Product::factory()->create(['stock' => 4, 'reserved_stock' => 0]);
    $auction = app(CreateSaleFromProductService::class)->handle($product, [
        'bid_fee' => 30,
        'reserve_stock' => true,
    ]);
    $bidder = Bidder::factory()->create();
    $pendingBid = AuctionBid::factory()->create([
        'auction_id' => $auction->id,
        'bidder_id' => $bidder->id,
        'status' => 'pending_payment',
    ]);
    $failedBid = AuctionBid::factory()->failed()->create([
        'auction_id' => $auction->id,
        'bidder_id' => $bidder->id,
    ]);
    $pendingAttempt = PaymentAttempt::factory()->create(['auction_bid_id' => $pendingBid->id]);
    $failedAttempt = PaymentAttempt::factory()->failed()->create(['auction_bid_id' => $failedBid->id]);
    $result = AuctionResult::factory()->create(['auction_id' => $auction->id]);
    $imageId = $auction->images()->value('id');

    expect($imageId)->not->toBeNull();

    app(DestroySaleService::class)->handle($auction);

    expect(Auction::query()->whereKey($auction->id)->exists())->toBeFalse()
        ->and(AuctionImage::query()->whereKey($imageId)->exists())->toBeFalse()
        ->and(AuctionBid::query()->whereKey([$pendingBid->id, $failedBid->id])->exists())->toBeFalse()
        ->and(PaymentAttempt::query()->whereKey([$pendingAttempt->id, $failedAttempt->id])->exists())->toBeFalse()
        ->and(AuctionResult::query()->whereKey($result->id)->exists())->toBeFalse()
        ->and(Bidder::query()->whereKey($bidder->id)->exists())->toBeTrue()
        ->and($product->fresh()->reserved_stock)->toBe(0)
        ->and($product->fresh()->availableStock())->toBe(4);
});

test('deleting a sale with paid participation fees is blocked', function () {
    $product = Product::factory()->create(['stock' => 3, 'reserved_stock' => 0]);
    $auction = app(CreateSaleFromProductService::class)->handle($product, [
        'bid_fee' => 25,
        'reserve_stock' => true,
    ]);
    AuctionBid::factory()->accepted()->create(['auction_id' => $auction->id]);

    expect(fn () => app(DestroySaleService::class)->handle($auction))
        ->toThrow(ValidationException::class);

    expect(Auction::query()->whereKey($auction->id)->exists())->toBeTrue()
        ->and($product->fresh()->reserved_stock)->toBe(1);
});

test('deleting a voided sale does not release another sale reserved stock', function () {
    $product = Product::factory()->create(['stock' => 5, 'reserved_stock' => 0]);
    $createSale = app(CreateSaleFromProductService::class);

    $mistaken = $createSale->handle($product, ['bid_fee' => 20]);
    $mistaken->update(['status' => 'voided', 'settled_at' => now()]);
    $createSale->releaseReservedStock($mistaken);

    $live = $createSale->handle($product->fresh(), ['bid_fee' => 20]);

    expect($product->fresh()->reserved_stock)->toBe(1);

    app(DestroySaleService::class)->handle($mistaken->fresh());

    expect(Auction::query()->whereKey($mistaken->id)->exists())->toBeFalse()
        ->and(Auction::query()->whereKey($live->id)->exists())->toBeTrue()
        ->and($product->fresh()->reserved_stock)->toBe(1);
});

test('a new sale can be created after the previous sale is deleted', function () {
    $product = Product::factory()->create(['stock' => 2, 'reserved_stock' => 0]);
    $createSale = app(CreateSaleFromProductService::class);
    $mistaken = $createSale->handle($product, ['bid_fee' => 40]);

    app(DestroySaleService::class)->handle($mistaken);

    $next = $createSale->handle($product->fresh(), ['bid_fee' => 40]);

    expect($next->id)->not->toBe($mistaken->id)
        ->and($next->status)->toBe('live')
        ->and($product->fresh()->reserved_stock)->toBe(1);
});
