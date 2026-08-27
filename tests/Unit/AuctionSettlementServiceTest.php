<?php

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Services\AuctionSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('failed and pending bids are ignored during settlement', function () {
    $auction = Auction::factory()->create([
        'status' => 'live',
        'ends_at' => now()->subMinute(),
    ]);

    AuctionBid::factory()->create([
        'auction_id' => $auction->id,
        'amount' => 5,
        'status' => 'pending_payment',
    ]);
    AuctionBid::factory()->failed()->create([
        'auction_id' => $auction->id,
        'amount' => 7,
    ]);
    $accepted = AuctionBid::factory()->accepted()->create([
        'auction_id' => $auction->id,
        'amount' => 12,
    ]);

    $result = app(AuctionSettlementService::class)->settle($auction);

    expect($result['successful_bid']?->is($accepted))->toBeTrue();
});

test('uniqueness snapshot names the current lowest unique accepted bid', function () {
    $auction = Auction::factory()->create([
        'status' => 'live',
        'ends_at' => now()->addHour(),
    ]);

    AuctionBid::factory()->accepted()->create([
        'auction_id' => $auction->id,
        'amount' => 10,
    ]);
    AuctionBid::factory()->accepted()->create([
        'auction_id' => $auction->id,
        'amount' => 10,
    ]);
    $leading = AuctionBid::factory()->accepted()->create([
        'auction_id' => $auction->id,
        'amount' => 15,
    ]);
    AuctionBid::factory()->create([
        'auction_id' => $auction->id,
        'amount' => 8,
        'status' => 'pending_payment',
    ]);

    $snapshot = app(AuctionSettlementService::class)->uniquenessSnapshot($auction);

    expect($snapshot['accepted_count'])->toBe(3)
        ->and($snapshot['unique_count'])->toBe(1)
        ->and($snapshot['amount_counts'][10])->toBe(2)
        ->and($snapshot['leading_bid']?->is($leading))->toBeTrue();
});
