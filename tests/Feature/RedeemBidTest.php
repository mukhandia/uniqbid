<?php

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionResult;
use App\Models\Bidder;
use App\Models\PaymentAttempt;
use App\Models\Shipment;
use App\Services\LogisticsService;
use App\Services\RedeemBidService;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

test('public redeem page is available', function () {
    $this->get(route('redeem'))
        ->assertOk()
        ->assertSee('Redeem for cash');
});

test('successful buy can be redeemed for product value minus sale amount', function () {
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
        ->assertSet('step', 'redeem')
        ->assertSet('salePrice', 45)
        ->assertSet('retailValue', 85000)
        ->assertSet('payoutAmount', 84955)
        ->call('confirmRedeem')
        ->assertSet('step', 'review')
        ->assertSee('Your redeem request has been sent for review.');

    $result->refresh();

    expect($result->fulfilment_status)->toBe(AuctionResult::FULFILMENT_REDEEMED)
        ->and($result->redemption_amount)->toBe(84955)
        ->and($result->redemption_payout_status)->toBe(AuctionResult::REDEMPTION_PAYOUT_PENDING)
        ->and($result->redemption_requested_at)->not->toBeNull()
        ->and($result->redeemed_at)->not->toBeNull()
        ->and(PaymentAttempt::query()->count())->toBe(0)
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
        'sale_payment_status' => AuctionResult::PAYMENT_AWAITING,
        'fulfilment_status' => AuctionResult::FULFILMENT_COLLECTION_READY,
        'buyer_display' => 'Grace W.',
    ]);

    app(LogisticsService::class)->syncFromAuctionResult($result->fresh(['auctionBid.bidder']));

    expect($result->fresh()->activeShipment()?->status)->toBe(Shipment::STATUS_READY);

    app(RedeemBidService::class)->redeem($result->fresh());

    expect($result->fresh()->fulfilment_status)->toBe(AuctionResult::FULFILMENT_REDEEMED)
        ->and($result->fresh()->redemption_amount)->toBe(39978)
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
        'redemption_amount' => 49980,
        'redeemed_at' => now(),
    ]);

    expect(fn () => app(RedeemBidService::class)->validateReference('BID-ALREADYDONE'))
        ->toThrow(ValidationException::class);
});

test('a paid successful buy cannot also be redeemed for cash', function () {
    $auction = Auction::factory()->create(['retail_value' => 50000, 'status' => 'closed', 'settled_at' => now()]);
    $bid = AuctionBid::factory()->accepted()->create([
        'auction_id' => $auction->id,
        'reference' => 'BID-ALREADYPAID',
        'amount' => 20,
    ]);
    $result = AuctionResult::factory()->create([
        'auction_id' => $auction->id,
        'auction_bid_id' => $bid->id,
        'sale_price' => 20,
        'sale_payment_status' => AuctionResult::PAYMENT_PAID,
        'fulfilment_status' => AuctionResult::FULFILMENT_PENDING,
    ]);

    expect(fn () => app(RedeemBidService::class)->redeem($result))
        ->toThrow(ValidationException::class);

    expect($result->fresh()->fulfilment_status)->toBe(AuctionResult::FULFILMENT_PENDING)
        ->and(Activity::query()->where('event', 'payout_paid')->exists())->toBeFalse();
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
        'redemption_amount' => 59967,
        'redemption_payout_status' => AuctionResult::REDEMPTION_PAYOUT_PENDING,
        'redeemed_at' => now(),
    ]);

    $updated = app(RedeemBidService::class)->markPayoutPaid($result);

    expect($updated->redemption_payout_status)->toBe(AuctionResult::REDEMPTION_PAYOUT_PAID);
});
