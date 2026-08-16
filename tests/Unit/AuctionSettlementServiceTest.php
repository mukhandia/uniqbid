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
