<?php

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\Bidder;
use App\Services\AuctionSettlementService;
use Illuminate\Support\Facades\Log;

test('lowest unique accepted bid becomes the successful buy', function () {
    Log::spy();

    $auction = Auction::factory()->create([
        'status' => 'live',
        'ends_at' => now()->subMinute(),
        'bids_count' => 0,
    ]);

    AuctionBid::factory()->accepted()->create([
        'auction_id' => $auction->id,
        'bidder_id' => Bidder::factory()->create(['name' => 'Amina Wanjiku', 'phone' => '254711111111'])->id,
        'amount' => 15,
    ]);
    AuctionBid::factory()->accepted()->create([
        'auction_id' => $auction->id,
        'bidder_id' => Bidder::factory()->create(['phone' => '254722222222'])->id,
        'amount' => 15,
    ]);
    $successful = AuctionBid::factory()->accepted()->create([
        'auction_id' => $auction->id,
        'bidder_id' => Bidder::factory()->create(['name' => 'Brian Otieno', 'phone' => '254733333333'])->id,
        'amount' => 22,
    ]);
    AuctionBid::factory()->accepted()->create([
        'auction_id' => $auction->id,
        'bidder_id' => Bidder::factory()->create(['phone' => '254744444444'])->id,
        'amount' => 41,
    ]);

    $result = app(AuctionSettlementService::class)->settle($auction);

    expect($result['successful_bid']?->is($successful))->toBeTrue()
        ->and($auction->fresh()->status)->toBe('closed')
        ->and($auction->fresh()->settled_at)->not->toBeNull()
        ->and($auction->fresh()->result->sale_price)->toBe(22)
        ->and($auction->fresh()->result->buyer_display)->toContain('Brian');

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => $message === 'notification_placeholder'
        && ($context['template'] ?? null) === 'successful_buyer');
});

test('sale extends when no unique accepted bid exists', function () {
    $auction = Auction::factory()->create([
        'status' => 'live',
        'ends_at' => now()->subMinute(),
    ]);

    AuctionBid::factory()->accepted()->create(['auction_id' => $auction->id, 'amount' => 10]);
    AuctionBid::factory()->accepted()->create(['auction_id' => $auction->id, 'amount' => 10]);

    $result = app(AuctionSettlementService::class)->settle($auction);

    expect($result['successful_bid'])->toBeNull()
        ->and($auction->fresh()->status)->toBe('live')
        ->and($auction->fresh()->settled_at)->toBeNull()
        ->and($auction->fresh()->ends_at->isFuture())->toBeTrue()
        ->and($auction->fresh()->result)->toBeNull();
});

test('settle ended command settles due sales', function () {
    $auction = Auction::factory()->create([
        'status' => 'live',
        'ends_at' => now()->subMinutes(5),
    ]);

    AuctionBid::factory()->accepted()->create([
        'auction_id' => $auction->id,
        'amount' => 30,
    ]);

    $this->artisan('auctions:settle-ended')
        ->assertSuccessful();

    expect($auction->fresh()->settled_at)->not->toBeNull()
        ->and($auction->fresh()->result->sale_price)->toBe(30);
});

test('sale can be re-offered to the next lowest unique bid', function () {
    $auction = Auction::factory()->create([
        'status' => 'live',
        'ends_at' => now()->subMinute(),
    ]);

    AuctionBid::factory()->accepted()->create(['auction_id' => $auction->id, 'amount' => 10]);
    $nextBid = AuctionBid::factory()->accepted()->create(['auction_id' => $auction->id, 'amount' => 20]);

    $settlement = app(AuctionSettlementService::class);
    $settlement->settle($auction);
    $reofferedBid = $settlement->reofferToNextBidder($auction);

    expect($reofferedBid?->is($nextBid))->toBeTrue()
        ->and($auction->fresh()->result->sale_price)->toBe(20);
});
