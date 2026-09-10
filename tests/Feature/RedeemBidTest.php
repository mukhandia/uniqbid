<?php

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionResult;
use App\Models\Bidder;
use App\Models\Shipment;
use App\Services\LogisticsService;
use App\Services\RedeemBidService;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

test('public redeem page is available', function () {
    $this->get(route('redeem'))
        ->assertOk()
        ->assertSee('Redeem Bid');
});

test('successful bid can be looked up paid and redeemed', function () {
    $auction = Auction::factory()->create([
        'name' => 'Redeemable Phone',
        'retail_value' => 85000,
        'status' => 'closed',
        'settled_at' => now(),
    ]);

    $bid = AuctionBid::factory()->accepted()->create([
        'auction_id' => $auction->id,
        'bidder_id' => Bidder::factory()->create(['name' => 'Grace Wanjiku'])->id,
        'amount' => 45,
        'reference' => 'BID-REDEEMTEST',
    ]);

    $result = AuctionResult::factory()->create([
        'auction_id' => $auction->id,
        'auction_bid_id' => $bid->id,
        'name' => $auction->name,
        'sale_price' => 45,
        'sale_payment_status' => AuctionResult::PAYMENT_AWAITING,
        'fulfilment_status' => AuctionResult::FULFILMENT_PENDING,
        'buyer_display' => 'Grace W.',
    ]);

    Livewire::test('pages::redeem')
        ->set('reference', 'BID-REDEEMTEST')
        ->call('lookup')
        ->assertSet('step', 'pay')
        ->assertSet('salePrice', 45)
        ->assertSet('retailValue', 85000)
        ->call('startPayment')
        ->assertSet('step', 'simulate')
        ->call('simulateSuccess')
        ->assertSet('step', 'redeem')
        ->assertSet('salePaid', true)
        ->call('confirmRedeem')
        ->assertSet('step', 'done');

    $result->refresh();

    expect($result->sale_payment_status)->toBe(AuctionResult::PAYMENT_PAID)
        ->and($result->fulfilment_status)->toBe(AuctionResult::FULFILMENT_REDEEMED)
        ->and($result->redemption_amount)->toBe(85000)
        ->and($result->redemption_payout_status)->toBe(AuctionResult::REDEMPTION_PAYOUT_PENDING)
        ->and($result->redeemed_at)->not->toBeNull()
        ->and($result->shipments()->latest('id')->first()?->status)->toBe(Shipment::STATUS_REDEEMED);
});

test('redeem after ready for collection marks logistics as redeemed', function () {
    $auction = Auction::factory()->create([
        'name' => 'Ready Then Redeem',
        'retail_value' => 40000,
        'status' => 'closed',
        'settled_at' => now(),
    ]);
    $bid = AuctionBid::factory()->accepted()->create([
        'auction_id' => $auction->id,
        'amount' => 22,
        'reference' => 'BID-READYREDEEM',
    ]);
    $result = AuctionResult::factory()->create([
        'auction_id' => $auction->id,
        'auction_bid_id' => $bid->id,
        'sale_price' => 22,
        'sale_payment_status' => AuctionResult::PAYMENT_PAID,
        'fulfilment_status' => AuctionResult::FULFILMENT_COLLECTION_READY,
        'buyer_display' => 'Grace W.',
    ]);

    app(LogisticsService::class)->syncFromAuctionResult($result->fresh(['auctionBid.bidder']));

    expect($result->fresh()->activeShipment()?->status)->toBe(Shipment::STATUS_READY);

    app(RedeemBidService::class)->redeem($result->fresh());

    expect($result->fresh()->fulfilment_status)->toBe(AuctionResult::FULFILMENT_REDEEMED)
        ->and($result->fresh()->shipments()->latest('id')->first()?->status)->toBe(Shipment::STATUS_REDEEMED);
});

test('redeem service rejects already redeemed results', function () {
    $auction = Auction::factory()->create(['retail_value' => 50000, 'status' => 'closed', 'settled_at' => now()]);
    $bid = AuctionBid::factory()->accepted()->create([
        'auction_id' => $auction->id,
        'reference' => 'BID-ALREADYDONE',
        'amount' => 20,
    ]);
    AuctionResult::factory()->create([
        'auction_id' => $auction->id,
        'auction_bid_id' => $bid->id,
        'sale_price' => 20,
        'sale_payment_status' => AuctionResult::PAYMENT_PAID,
        'fulfilment_status' => AuctionResult::FULFILMENT_REDEEMED,
        'redemption_amount' => 50000,
        'redeemed_at' => now(),
    ]);

    expect(fn () => app(RedeemBidService::class)->validateReference('BID-ALREADYDONE'))
        ->toThrow(ValidationException::class);
});

test('finance can mark redemption payout as paid', function () {
    $auction = Auction::factory()->create(['retail_value' => 60000, 'status' => 'closed', 'settled_at' => now()]);
    $bid = AuctionBid::factory()->accepted()->create(['auction_id' => $auction->id, 'amount' => 33]);
    $result = AuctionResult::factory()->create([
        'auction_id' => $auction->id,
        'auction_bid_id' => $bid->id,
        'sale_price' => 33,
        'sale_payment_status' => AuctionResult::PAYMENT_PAID,
        'fulfilment_status' => AuctionResult::FULFILMENT_REDEEMED,
        'redemption_amount' => 60000,
        'redemption_payout_status' => AuctionResult::REDEMPTION_PAYOUT_PENDING,
        'redeemed_at' => now(),
    ]);

    $updated = app(RedeemBidService::class)->markPayoutPaid($result);

    expect($updated->redemption_payout_status)->toBe(AuctionResult::REDEMPTION_PAYOUT_PAID);
});
