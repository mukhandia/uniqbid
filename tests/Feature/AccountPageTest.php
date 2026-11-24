<?php

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionResult;
use App\Models\Bidder;
use App\Models\CustomerControl;
use App\Models\User;
use Livewire\Livewire;

test('guests see sign in on the account page', function () {
    $this->get(route('account'))
        ->assertOk()
        ->assertSee('Sign in with mobile')
        ->assertSee('Guest')
        ->assertDontSee('Go to dashboard');
});

test('admin users see dashboard and profile links', function () {
    $user = User::factory()->admin()->create([
        'email' => 'admin@uniqbid.test',
    ]);

    $this->actingAs($user)
        ->get(route('account'))
        ->assertOk()
        ->assertSee('Go to dashboard')
        ->assertSee('Profile settings')
        ->assertSee('admin@uniqbid.test')
        ->assertDontSee('Sign in with mobile');
});

test('customers see profile controls and bid history', function () {
    $user = User::factory()->create([
        'phone' => '254712345678',
        'phone_verified_at' => now(),
        'name' => 'Jane Bidder',
        'email' => 'jane@example.com',
    ]);

    $bidder = Bidder::factory()->create([
        'user_id' => $user->id,
        'phone' => $user->phone,
        'name' => $user->name,
        'email' => $user->email,
    ]);

    $auction = Auction::factory()->create([
        'name' => 'Weekend Gadget Sale',
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    AuctionBid::factory()->accepted()->create([
        'auction_id' => $auction->id,
        'bidder_id' => $bidder->id,
        'amount' => 25,
        'reference' => 'BID-TEST1234',
    ]);

    $this->actingAs($user)
        ->get(route('account'))
        ->assertOk()
        ->assertSee('Jane Bidder')
        ->assertSee('Profile')
        ->assertSee('Settings')
        ->assertSee('Sales & Auction history')
        ->assertSee('Vendor listings')
        ->assertDontSee('Go to dashboard')
        ->assertDontSee('Profile settings');

    Livewire::actingAs($user)
        ->test('pages::account')
        ->assertSee('Jane Bidder')
        ->call('showSection', 'settings')
        ->assertSee('Participation fee limit')
        ->call('showSection', 'history')
        ->assertSee('Weekend Gadget Sale')
        ->assertSee('BID-TEST1234')
        ->assertSee('Open');
});

test('customers can filter sales history by product name or bid number', function () {
    $user = User::factory()->create([
        'phone' => '254712345678',
        'phone_verified_at' => now(),
    ]);
    $bidder = Bidder::factory()->create([
        'user_id' => $user->id,
        'phone' => $user->phone,
    ]);

    $keep = Auction::factory()->create(['name' => 'Keep Phone Sale', 'status' => 'live']);
    $hide = Auction::factory()->create(['name' => 'Hide Laptop Sale', 'status' => 'live']);

    AuctionBid::factory()->accepted()->create([
        'auction_id' => $keep->id,
        'bidder_id' => $bidder->id,
        'amount' => 10,
        'reference' => 'BID-KEEP0001',
    ]);
    AuctionBid::factory()->accepted()->create([
        'auction_id' => $hide->id,
        'bidder_id' => $bidder->id,
        'amount' => 12,
        'reference' => 'BID-HIDE0002',
    ]);

    Livewire::actingAs($user)
        ->test('pages::account')
        ->set('section', 'history')
        ->assertSee('Keep Phone Sale')
        ->assertSee('Hide Laptop Sale')
        ->set('historySearch', 'Phone')
        ->assertSee('Keep Phone Sale')
        ->assertDontSee('Hide Laptop Sale')
        ->set('historySearch', 'BID-HIDE')
        ->assertSee('Hide Laptop Sale')
        ->assertDontSee('Keep Phone Sale');
});

test('customers can update profile from my account', function () {
    $user = User::factory()->marketingConsent()->create([
        'phone' => '254712345678',
        'phone_verified_at' => now(),
    ]);

    Bidder::factory()->create([
        'user_id' => $user->id,
        'phone' => $user->phone,
        'name' => $user->name,
        'email' => $user->email,
    ]);

    Livewire::actingAs($user)
        ->test('pages::account')
        ->set('name', 'Updated Name')
        ->set('email', 'updated@example.com')
        ->set('whatsappNumber', '0711222333')
        ->call('saveProfile')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toBe('Updated Name')
        ->and($user->email)->toBe('updated@example.com')
        ->and($user->marketing_consent)->toBeTrue()
        ->and($user->bidder->whatsapp_number)->toBe('254711222333');
});

test('customers can save their profile without an email', function () {
    $user = User::factory()->create([
        'phone' => '254712345678',
        'phone_verified_at' => now(),
        'email' => 'old@example.com',
    ]);

    Bidder::factory()->create([
        'user_id' => $user->id,
        'phone' => $user->phone,
        'email' => 'old@example.com',
    ]);

    Livewire::actingAs($user)
        ->test('pages::account')
        ->set('email', '')
        ->call('saveProfile')
        ->assertHasNoErrors();

    expect($user->fresh()->email)->toBeNull()
        ->and($user->fresh()->bidder->email)->toBeNull();
});

test('my account no longer shows the updates and offers checkboxes', function () {
    $user = User::factory()->create([
        'phone' => '254712345678',
        'phone_verified_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::account')
        ->assertSee('Email (optional)')
        ->assertDontSee('Receive UniqBid updates and offers')
        ->assertDontSee('Send sale and account updates by SMS')
        ->assertDontSee('Send sale and account updates by WhatsApp');
});

test('saving the profile keeps the consent given when bidding', function () {
    $user = User::factory()->marketingConsent()->create([
        'phone' => '254712345678',
        'phone_verified_at' => now(),
        'sms_notifications' => true,
        'whatsapp_notifications' => true,
    ]);

    Livewire::actingAs($user)
        ->test('pages::account')
        ->set('name', 'Still Consenting')
        ->call('saveProfile')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->marketing_consent)->toBeTrue()
        ->and($user->sms_notifications)->toBeTrue()
        ->and($user->whatsapp_notifications)->toBeTrue();
});

test('customers can set an optional participation fee limit', function () {
    $user = User::factory()->create([
        'phone' => '254712345678',
        'phone_verified_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::account')
        ->set('participationFeeLimit', 500)
        ->set('limitPeriod', 'daily')
        ->call('saveSpendLimit')
        ->assertHasNoErrors();

    $control = CustomerControl::query()->where('user_id', $user->id)->first();

    expect($control)->not->toBeNull()
        ->and($control->participation_fee_limit)->toBe(500)
        ->and($control->limit_period)->toBe('daily');
});

test('settled bids hide win or loss and winning-amount payment on my account', function () {
    $user = User::factory()->create([
        'phone' => '254712345678',
        'phone_verified_at' => now(),
    ]);
    $bidder = Bidder::factory()->create([
        'user_id' => $user->id,
        'phone' => $user->phone,
    ]);
    $wonAuction = Auction::factory()->create([
        'name' => 'Winning Pencil',
        'status' => 'closed',
        'settled_at' => now(),
        'retail_value' => 20,
    ]);
    $lostAuction = Auction::factory()->create([
        'name' => 'Losing Pencil',
        'status' => 'closed',
        'settled_at' => now(),
    ]);
    $wonBid = AuctionBid::factory()->accepted()->create([
        'auction_id' => $wonAuction->id,
        'bidder_id' => $bidder->id,
        'amount' => 1,
        'reference' => 'BID-WONHIDE01',
    ]);
    AuctionBid::factory()->accepted()->create([
        'auction_id' => $lostAuction->id,
        'bidder_id' => $bidder->id,
        'amount' => 5,
        'reference' => 'BID-LOSTHIDE1',
    ]);
    AuctionResult::factory()->create([
        'auction_id' => $wonAuction->id,
        'auction_bid_id' => $wonBid->id,
        'sale_price' => 1,
        'fulfilment_status' => AuctionResult::FULFILMENT_REDEEMED,
        'redemption_amount' => 19,
        'redemption_payout_status' => AuctionResult::REDEMPTION_PAYOUT_PENDING,
    ]);
    AuctionResult::factory()->create([
        'auction_id' => $lostAuction->id,
        'auction_bid_id' => null,
        'sale_price' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('account', ['section' => 'history']))
        ->assertOk()
        ->assertSee('Winning Pencil')
        ->assertSee('Losing Pencil')
        ->assertSee('Bid Successfully Placed')
        ->assertDontSee('Successful buy')
        ->assertDontSee('Not successful')
        ->assertDontSee('Pay a winning amount')
        ->assertDontSee('Pay with M-Pesa')
        ->assertDontSee('Cash payout')
        ->assertDontSee('Try again');
});
