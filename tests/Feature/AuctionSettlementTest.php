<?php

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionResult;
use App\Models\Bidder;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\User;
use App\Services\AuctionSettlementService;
use App\Services\CreateSaleFromProductService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

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
        ->and($result['advanced_round'])->toBeFalse()
        ->and($auction->fresh()->status)->toBe('closed')
        ->and($auction->fresh()->settled_at)->not->toBeNull()
        ->and($auction->fresh()->result->sale_price)->toBe(22)
        ->and($auction->fresh()->result->buyer_display)->toContain('Brian');

    Log::shouldNotHaveReceived('info', fn (string $message, array $context): bool => $message === 'notification_placeholder'
        && ($context['template'] ?? null) === 'successful_buyer');
});

test('sale advances to the next round when no unique accepted bid exists', function () {
    $auction = Auction::factory()->create([
        'status' => 'live',
        'ends_at' => now()->subMinute(),
        'current_round' => 1,
        'duration_hours' => 30,
    ]);

    AuctionBid::factory()->accepted()->create(['auction_id' => $auction->id, 'amount' => 10]);
    AuctionBid::factory()->accepted()->create(['auction_id' => $auction->id, 'amount' => 10]);

    $result = app(AuctionSettlementService::class)->settle($auction);
    $fresh = $auction->fresh('rounds');

    expect($result['successful_bid'])->toBeNull()
        ->and($result['advanced_round'])->toBeTrue()
        ->and($fresh->status)->toBe('live')
        ->and($fresh->settled_at)->toBeNull()
        ->and($fresh->current_round)->toBe(2)
        ->and($fresh->ends_at->isFuture())->toBeTrue()
        ->and($fresh->result)->toBeNull()
        ->and($fresh->rounds)->toHaveCount(2)
        ->and($fresh->rounds->firstWhere('round', 1)?->outcome)->toBe('no_unique');
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

test('re-offering closes the first winner redeem request', function () {
    $auction = Auction::factory()->create([
        'name' => 'Reoffer Pencil',
        'status' => 'closed',
        'settled_at' => now(),
        'retail_value' => 40,
    ]);
    $first = AuctionBid::factory()->accepted()->create(['auction_id' => $auction->id, 'amount' => 10]);
    $next = AuctionBid::factory()->accepted()->create(['auction_id' => $auction->id, 'amount' => 20]);
    $result = AuctionResult::factory()->create([
        'auction_id' => $auction->id,
        'auction_bid_id' => $first->id,
        'sale_price' => 10,
        'fulfilment_status' => AuctionResult::FULFILMENT_REDEEMED,
        'outcome_choice' => 'redeem',
        'redemption_amount' => 30,
        'redemption_payout_status' => AuctionResult::REDEMPTION_PAYOUT_PENDING,
        'redemption_requested_at' => now(),
    ]);

    $reoffered = app(AuctionSettlementService::class)->reofferToNextBidder($auction->fresh());

    expect($reoffered?->is($next))->toBeTrue()
        ->and($result->fresh()->auction_bid_id)->toBe($next->id)
        ->and($result->fresh()->fulfilment_status)->toBe(AuctionResult::FULFILMENT_PENDING)
        ->and($result->fresh()->redemption_requested_at)->toBeNull();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::admin.auction-bids', ['auction' => $auction])
        ->assertDontSee('Initiate payment');
});

test('re-offer waits while a cash payout is still with mpesa', function () {
    $auction = Auction::factory()->create([
        'status' => 'closed',
        'settled_at' => now(),
    ]);
    $first = AuctionBid::factory()->accepted()->create(['auction_id' => $auction->id, 'amount' => 10]);
    AuctionBid::factory()->accepted()->create(['auction_id' => $auction->id, 'amount' => 20]);
    AuctionResult::factory()->create([
        'auction_id' => $auction->id,
        'auction_bid_id' => $first->id,
        'sale_price' => 10,
        'fulfilment_status' => AuctionResult::FULFILMENT_REDEEMED,
        'redemption_amount' => 30,
        'redemption_payout_status' => AuctionResult::REDEMPTION_PAYOUT_PENDING,
        'redemption_requested_at' => now(),
    ]);
    PaymentAttempt::query()->create([
        'auction_bid_id' => $first->id,
        'provider' => 'mpesa_daraja_b2c',
        'reference' => 'RDMWAIT0001',
        'amount' => 30,
        'status' => 'initiated',
        'request_payload' => ['purpose' => 'redeem_payout'],
    ]);

    expect(fn () => app(AuctionSettlementService::class)->reofferToNextBidder($auction->fresh()))
        ->toThrow(ValidationException::class);

    expect($auction->fresh()->result->auction_bid_id)->toBe($first->id);
});

test('settling a successful sale decrements stock and can go negative', function () {
    $product = Product::factory()->create([
        'stock' => 0,
        'reserved_stock' => 0,
    ]);

    $auction = app(CreateSaleFromProductService::class)->handle($product, [
        'bid_fee' => 30,
        'reserve_stock' => true,
        'ends_in_hours' => 1,
    ]);

    $auction->update([
        'ends_at' => now()->subMinute(),
        'status' => 'live',
    ]);

    expect($product->fresh()->stock)->toBe(0)
        ->and($product->fresh()->reserved_stock)->toBe(1);

    AuctionBid::factory()->accepted()->create([
        'auction_id' => $auction->id,
        'amount' => 25,
    ]);

    app(AuctionSettlementService::class)->settle($auction->fresh());

    $nextSale = Auction::query()
        ->where('product_id', $product->id)
        ->whereNull('settled_at')
        ->where('status', 'live')
        ->whereKeyNot($auction->id)
        ->first();

    expect($product->fresh()->stock)->toBe(-1)
        ->and($product->fresh()->reserved_stock)->toBe(1)
        ->and($auction->fresh()->settled_at)->not->toBeNull()
        ->and($nextSale)->not->toBeNull()
        ->and($nextSale->duration_hours)->toEqual(1.0)
        ->and($nextSale->ends_at->isFuture())->toBeTrue();
});

test('closing a running sale settles the lowest unique bid immediately', function () {
    $auction = Auction::factory()->create([
        'status' => 'live',
        'ends_at' => now()->addHours(5),
        'duration_hours' => 5,
    ]);

    AuctionBid::factory()->accepted()->create([
        'auction_id' => $auction->id,
        'amount' => 9,
    ]);

    $result = app(AuctionSettlementService::class)->closeNow($auction);

    expect($result['successful_bid']?->amount)->toBe(9)
        ->and($result['advanced_round'])->toBeFalse()
        ->and($auction->fresh()->settled_at)->not->toBeNull()
        ->and($auction->fresh()->result->sale_price)->toBe(9)
        ->and($auction->fresh()->ends_at->isPast())->toBeTrue();
});

test('closing a running sale with no unique bid starts the next round', function () {
    $auction = Auction::factory()->create([
        'status' => 'live',
        'ends_at' => now()->addHours(8),
        'current_round' => 1,
        'duration_hours' => 8,
    ]);

    AuctionBid::factory()->accepted()->create(['auction_id' => $auction->id, 'amount' => 4]);
    AuctionBid::factory()->accepted()->create(['auction_id' => $auction->id, 'amount' => 4]);

    $result = app(AuctionSettlementService::class)->closeNow($auction);
    $fresh = $auction->fresh();

    expect($result['advanced_round'])->toBeTrue()
        ->and($result['successful_bid'])->toBeNull()
        ->and($fresh->status)->toBe('live')
        ->and($fresh->settled_at)->toBeNull()
        ->and($fresh->current_round)->toBe(2)
        ->and($fresh->ends_at->isFuture())->toBeTrue();
});

test('a sale that has already ended cannot be closed early', function () {
    $auction = Auction::factory()->create([
        'status' => 'live',
        'ends_at' => now()->subMinute(),
    ]);

    expect(fn () => app(AuctionSettlementService::class)->closeNow($auction))
        ->toThrow(ValidationException::class);
});

test('an admin can close a running sale from the sale page', function () {
    $admin = User::factory()->admin()->create();
    $auction = Auction::factory()->create([
        'name' => 'Early Close Sale',
        'status' => 'live',
        'ends_at' => now()->addHours(5),
    ]);

    AuctionBid::factory()->accepted()->create([
        'auction_id' => $auction->id,
        'amount' => 12,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.auction-bids', ['auction' => $auction])
        ->assertSee('Close sale now')
        ->call('closeNow')
        ->assertHasNoErrors();

    expect($auction->fresh()->settled_at)->not->toBeNull()
        ->and($auction->fresh()->result->sale_price)->toBe(12);
});

test('settling a successful sale without a product does not auto-start another sale', function () {
    $auction = Auction::factory()->create([
        'product_id' => null,
        'status' => 'live',
        'ends_at' => now()->subMinute(),
        'duration_hours' => 12,
    ]);

    AuctionBid::factory()->accepted()->create([
        'auction_id' => $auction->id,
        'amount' => 18,
    ]);

    $result = app(AuctionSettlementService::class)->settle($auction);

    expect($result['next_sale'])->toBeNull()
        ->and(Auction::query()->where('status', 'live')->whereKeyNot($auction->id)->count())->toBe(0);
});
