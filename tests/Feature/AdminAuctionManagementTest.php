<?php

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\Bidder;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\User;
use App\Services\AuctionSettlementService;
use App\Services\CreateSaleFromProductService;
use Livewire\Livewire;

test('guests cannot access admin sales pages', function () {
    $this->get(route('admin.auctions'))->assertRedirect(route('login'));
});

test('non admins cannot access admin sales pages', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get(route('admin.auctions'))
        ->assertForbidden();
});

test('admins can view sales and bid payment records', function () {
    $admin = User::factory()->admin()->create();
    $auction = Auction::factory()->create([
        'status' => 'live',
        'ends_at' => now()->addHour(),
    ]);
    $bidder = Bidder::factory()->marketingConsent()->create([
        'name' => 'Grace Njeri',
        'phone' => '254755555555',
        'email' => 'grace@example.com',
    ]);
    $bid = AuctionBid::factory()->accepted()->create([
        'auction_id' => $auction->id,
        'bidder_id' => $bidder->id,
        'amount' => 45,
    ]);
    PaymentAttempt::factory()->succeeded()->create([
        'auction_bid_id' => $bid->id,
        'amount' => $auction->bid_fee,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.auctions'))
        ->assertSuccessful()
        ->assertSee($auction->name)
        ->assertSee('Live')
        ->assertSee('fee paid')
        ->assertSee('awaiting payment')
        ->assertSeeHtml('wire:poll.180s.visible')
        ->assertSee('Open')
        ->assertSee(route('admin.auction-bids', $auction), false);

    $this->actingAs($admin)
        ->get(route('admin.auction-bids', $auction))
        ->assertSuccessful()
        ->assertSee('Incoming bids')
        ->assertSee('Current lowest unique')
        ->assertSee('Grace Njeri')
        ->assertSee('254755555555')
        ->assertSee('grace@example.com')
        ->assertSee('Lowest unique')
        ->assertSee('Yes')
        ->assertSeeHtml('wire:poll.180s.visible');
});

test('admins can view guest and verified customer profiles', function () {
    $admin = User::factory()->admin()->create();

    $guest = Bidder::factory()->create([
        'name' => 'Guest Bidder',
        'phone' => '254711111111',
        'email' => 'guest@example.com',
    ]);

    $customer = User::factory()->create([
        'name' => 'Verified Customer',
        'email' => 'customer@example.com',
        'phone' => '254722222222',
        'phone_verified_at' => now(),
    ]);

    $verifiedBidder = Bidder::factory()->create([
        'user_id' => $customer->id,
        'name' => $customer->name,
        'phone' => $customer->phone,
        'email' => $customer->email,
    ]);

    $auction = Auction::factory()->create();
    AuctionBid::factory()->create([
        'auction_id' => $auction->id,
        'bidder_id' => $verifiedBidder->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.customers'))
        ->assertSuccessful()
        ->assertSee('Guest Bidder')
        ->assertSee('Verified Customer')
        ->assertSee('Guest profile')
        ->assertSee('Verified account')
        ->assertSee($auction->name);

    expect($guest->user)->toBeNull();
});

test('admins can settle an ended sale from the admin page', function () {
    $admin = User::factory()->admin()->create();
    $auction = Auction::factory()->create([
        'status' => 'live',
        'ends_at' => now()->subMinute(),
    ]);
    AuctionBid::factory()->accepted()->create([
        'auction_id' => $auction->id,
        'amount' => 18,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.auctions')
        ->call('settle', $auction->id);

    expect($auction->fresh()->settled_at)->not->toBeNull()
        ->and($auction->fresh()->settled_by)->toBe($admin->id)
        ->and($auction->fresh()->result->sale_price)->toBe(18);
});

test('admins can update customer verification status', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->create([
        'phone' => '254712345678',
        'phone_verified_at' => now(),
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.customer-verifications')
        ->call('updateStatus', $customer->id, 'verified');

    expect($customer->fresh()->customerControl?->verification_status)->toBe('verified');
});

test('admins can track a successful buy payment and fulfilment', function () {
    $admin = User::factory()->admin()->create();
    $auction = Auction::factory()->create([
        'status' => 'live',
        'ends_at' => now()->subMinute(),
    ]);
    $bid = AuctionBid::factory()->accepted()->create([
        'auction_id' => $auction->id,
        'amount' => 18,
    ]);

    app(AuctionSettlementService::class)->settle($auction, $admin);

    $this->actingAs($admin)
        ->get(route('admin.auction-bids', $auction))
        ->assertSuccessful()
        ->assertSee('Successful bidder')
        ->assertSee($bid->bidder->name)
        ->assertSee('Mark sale paid')
        ->assertSee('Mark picked up')
        ->assertSee('Process');

    Livewire::actingAs($admin)
        ->test('pages::admin.auction-bids', ['auction' => $auction])
        ->call('markCollected')
        ->assertHasNoErrors();

    expect($auction->fresh()->result->fulfilment_status)->toBe('pending');

    Livewire::actingAs($admin)
        ->test('pages::admin.auction-bids', ['auction' => $auction])
        ->call('markSalePaid')
        ->call('markCollectionReady')
        ->call('markCollected')
        ->assertHasNoErrors();

    $result = $auction->fresh()->result;

    expect($result->auction_bid_id)->toBe($bid->id)
        ->and($result->sale_payment_status)->toBe('paid')
        ->and($result->sale_payment_paid_at)->not->toBeNull()
        ->and($result->fulfilment_status)->toBe('collected')
        ->and($result->fulfilment_due_at)->not->toBeNull()
        ->and($result->activeShipment()?->status)->toBe(\App\Models\Shipment::STATUS_COLLECTED)
        ->and($result->activeShipment()?->method)->toBe(\App\Models\Shipment::METHOD_WESTLANDS_COLLECTION);
});

test('admins can filter sales that still need payment or pickup', function () {
    $admin = User::factory()->admin()->create();
    $openSale = Auction::factory()->create([
        'name' => 'Still Live Speaker Sale',
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);
    $toProcess = Auction::factory()->create([
        'name' => 'Paid Watch Waiting Collection',
        'status' => 'closed',
        'ends_at' => now()->subDay(),
        'settled_at' => now()->subDay(),
    ]);
    $toProcess->result()->create([
        'slug' => 'paid-watch-waiting-collection-result',
        'name' => $toProcess->name,
        'category' => $toProcess->category,
        'image' => '/images/auctions/iphone-15-pro.png',
        'sale_price' => 40,
        'sale_payment_status' => 'paid',
        'fulfilment_status' => 'collection_ready',
        'buyer_display' => 'Amina 12.',
        'sold_on' => now()->toDateString(),
    ]);
    $complete = Auction::factory()->create([
        'name' => 'Collected Camera Sale',
        'status' => 'closed',
        'ends_at' => now()->subDays(2),
        'settled_at' => now()->subDays(2),
    ]);
    $complete->result()->create([
        'slug' => 'collected-camera-sale-result',
        'name' => $complete->name,
        'category' => $complete->category,
        'image' => '/images/auctions/iphone-15-pro.png',
        'sale_price' => 55,
        'sale_payment_status' => 'paid',
        'fulfilment_status' => 'collected',
        'buyer_display' => 'James 90.',
        'sold_on' => now()->toDateString(),
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.auctions')
        ->set('status', 'to_process')
        ->assertSee('Paid Watch Waiting Collection')
        ->assertDontSee('Still Live Speaker Sale')
        ->assertDontSee('Collected Camera Sale');
});

test('admins can set one live sale as the homepage hero', function () {
    $admin = User::factory()->admin()->create();
    $previous = Auction::factory()->onAuctionChannel()->create([
        'name' => 'Previous Homepage Sale',
        'status' => 'live',
        'ends_at' => now()->addHours(2),
        'is_featured' => true,
    ]);
    $next = Auction::factory()->onAuctionChannel()->create([
        'name' => 'New Homepage Sale',
        'status' => 'live',
        'ends_at' => now()->addDay(),
        'is_featured' => false,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.auctions')
        ->assertSee('Set as homepage hero')
        ->assertSee('Remove homepage hero')
        ->call('toggleHomepageHero', $next->id);

    expect($next->fresh()->is_featured)->toBeTrue()
        ->and($previous->fresh()->is_featured)->toBeFalse();
});

test('admins can remove a sale from the homepage hero', function () {
    $admin = User::factory()->admin()->create();
    $auction = Auction::factory()->onAuctionChannel()->create([
        'status' => 'live',
        'ends_at' => now()->addHour(),
        'is_featured' => true,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.auctions')
        ->call('toggleHomepageHero', $auction->id);

    expect($auction->fresh()->is_featured)->toBeFalse();
});

test('admins can filter sales by search status and category', function () {
    $admin = User::factory()->admin()->create();
    $liveSale = Auction::factory()->create([
        'name' => 'Purple Telescope Sale',
        'category' => 'Electronics',
        'status' => 'live',
        'ends_at' => now()->addDay(),
        'is_featured' => true,
    ]);
    $endedSale = Auction::factory()->create([
        'name' => 'Amber Camera Closeout',
        'category' => 'Vehicles',
        'status' => 'live',
        'ends_at' => now()->subHour(),
        'settled_at' => null,
        'is_featured' => false,
    ]);
    $settledSale = Auction::factory()->create([
        'name' => 'Settled Watch Bundle',
        'category' => 'Home',
        'status' => 'closed',
        'ends_at' => now()->subDay(),
        'settled_at' => now()->subDay(),
        'is_featured' => false,
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::admin.auctions')
        ->assertSee('Purple Telescope Sale')
        ->assertSee('Amber Camera Closeout')
        ->assertSee('Settled Watch Bundle')
        ->set('search', 'Purple Telescope')
        ->assertSee('Purple Telescope Sale')
        ->assertDontSee('Amber Camera Closeout')
        ->assertDontSee('Settled Watch Bundle')
        ->call('clearFilters')
        ->assertSee('Purple Telescope Sale')
        ->assertSee('Amber Camera Closeout')
        ->set('status', 'needs_settle')
        ->assertSee('Amber Camera Closeout')
        ->assertDontSee('Purple Telescope Sale')
        ->assertDontSee('Settled Watch Bundle')
        ->set('status', 'live')
        ->set('category', '')
        ->assertSee('Purple Telescope Sale')
        ->assertDontSee('Amber Camera Closeout')
        ->set('status', 'all')
        ->set('category', 'Vehicles')
        ->assertSee('Amber Camera Closeout')
        ->assertDontSee('Purple Telescope Sale')
        ->set('category', '')
        ->set('status', 'homepage')
        ->assertSee('Purple Telescope Sale')
        ->assertDontSee('Amber Camera Closeout');

    $this->get(route('admin.auctions', ['status' => 'needs_settle']))
        ->assertSuccessful()
        ->assertSee($endedSale->name)
        ->assertDontSee($liveSale->name)
        ->assertDontSee($settledSale->name);
});

test('admins can delete a mistaken sale and return reserved stock', function () {
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->create(['stock' => 4, 'reserved_stock' => 0]);
    $auction = app(CreateSaleFromProductService::class)->handle($product, [
        'bid_fee' => 30,
        'reserve_stock' => true,
    ]);
    AuctionBid::factory()->create([
        'auction_id' => $auction->id,
        'status' => 'pending_payment',
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.auctions')
        ->assertSee('Permanently delete this sale')
        ->call('delete', $auction->id);

    expect(Auction::query()->whereKey($auction->id)->exists())->toBeFalse()
        ->and($product->fresh()->reserved_stock)->toBe(0);
});

test('admins cannot delete a sale after a participation fee has been paid', function () {
    $admin = User::factory()->admin()->create();
    $auction = Auction::factory()->create([
        'status' => 'live',
        'ends_at' => now()->addHour(),
    ]);
    AuctionBid::factory()->accepted()->create([
        'auction_id' => $auction->id,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.auctions')
        ->assertDontSee('Permanently delete this sale')
        ->call('delete', $auction->id);

    expect(Auction::query()->whereKey($auction->id)->exists())->toBeTrue();
});
