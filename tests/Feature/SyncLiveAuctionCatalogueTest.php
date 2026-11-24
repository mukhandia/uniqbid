<?php

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\Product;
use Illuminate\Support\Facades\Artisan;

test('sync command removes orphan live sales and shop-only live sales', function () {
    $keep = Auction::factory()->onAuctionChannel()->create([
        'name' => 'Keep On Catalogue',
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    $orphan = Auction::factory()->create([
        'name' => 'Orphan Demo Sale',
        'status' => 'live',
        'ends_at' => now()->addDay(),
        'product_id' => null,
        'settled_at' => null,
    ]);

    $staleLive = Auction::factory()->create([
        'name' => 'Stale Live Orphan',
        'status' => 'live',
        'ends_at' => now()->addHours(3),
        'product_id' => null,
        'settled_at' => now()->subHour(),
    ]);

    $shopOnly = Product::factory()->inShop()->create(['name' => 'Shop Watch']);
    $shopSale = Auction::factory()->create([
        'product_id' => $shopOnly->id,
        'name' => 'Shop Watch Sale',
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    Artisan::call('uniqbid:sync-live-catalogue');

    expect(Auction::query()->whereKey($keep->id)->exists())->toBeTrue()
        ->and(Auction::query()->whereKey($orphan->id)->exists())->toBeFalse()
        ->and(Auction::query()->whereKey($staleLive->id)->exists())->toBeFalse()
        ->and(Auction::query()->whereKey($shopSale->id)->exists())->toBeFalse()
        ->and(Auction::query()->liveOnAuctionChannel()->count())->toBe(1);
});

test('sync command voids live sales that have paid participation instead of deleting them', function () {
    $orphan = Auction::factory()->create([
        'name' => 'Paid Orphan Sale',
        'status' => 'live',
        'ends_at' => now()->addDay(),
        'product_id' => null,
        'settled_at' => null,
    ]);
    AuctionBid::factory()->accepted()->create(['auction_id' => $orphan->id]);

    Artisan::call('uniqbid:sync-live-catalogue');

    expect($orphan->fresh()->status)->toBe('voided')
        ->and($orphan->fresh()->settled_at)->not->toBeNull()
        ->and(Auction::query()->liveOnAuctionChannel()->count())->toBe(0);
});
