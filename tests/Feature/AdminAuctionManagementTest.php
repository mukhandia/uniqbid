<?php

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\Bidder;
use App\Models\PaymentAttempt;
use App\Models\User;
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
