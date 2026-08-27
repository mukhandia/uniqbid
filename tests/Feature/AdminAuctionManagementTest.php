<?php

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\Bidder;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Services\AuctionSettlementService;
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
        ->assertSee($auction->name);

    $this->actingAs($admin)
        ->get(route('admin.auction-bids', $auction))
        ->assertSuccessful()
        ->assertSee('Grace Njeri')
        ->assertSee('254755555555')
        ->assertSee('grace@example.com')
        ->assertSee('Yes');
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

    Livewire::actingAs($admin)
        ->test('pages::admin.auction-bids', ['auction' => $auction])
        ->set('salePaymentStatus', 'paid')
        ->set('fulfilmentStatus', 'collection_ready')
        ->call('updateFulfilment')
        ->assertHasNoErrors();

    $result = $auction->fresh()->result;

    expect($result->auction_bid_id)->toBe($bid->id)
        ->and($result->sale_payment_status)->toBe('paid')
        ->and($result->sale_payment_paid_at)->not->toBeNull()
        ->and($result->fulfilment_status)->toBe('collection_ready')
        ->and($result->fulfilment_due_at)->not->toBeNull();
});

test('admins can set one live sale as the homepage hero', function () {
    $admin = User::factory()->admin()->create();
    $previous = Auction::factory()->create([
        'name' => 'Previous Homepage Sale',
        'status' => 'live',
        'ends_at' => now()->addHours(2),
        'is_featured' => true,
    ]);
    $next = Auction::factory()->create([
        'name' => 'New Homepage Sale',
        'status' => 'live',
        'ends_at' => now()->addDay(),
        'is_featured' => false,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.auctions')
        ->assertSee('Set as homepage')
        ->assertSee('Remove from homepage')
        ->call('toggleHomepageHero', $next->id);

    expect($next->fresh()->is_featured)->toBeTrue()
        ->and($previous->fresh()->is_featured)->toBeFalse();
});

test('admins can remove a sale from the homepage hero', function () {
    $admin = User::factory()->admin()->create();
    $auction = Auction::factory()->create([
        'status' => 'live',
        'ends_at' => now()->addHour(),
        'is_featured' => true,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.auctions')
        ->call('toggleHomepageHero', $auction->id);

    expect($auction->fresh()->is_featured)->toBeFalse();
});
