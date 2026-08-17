<?php

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\Bidder;
use App\Models\CustomerControl;
use App\Models\PaymentAttempt;
use App\Models\User;
use Livewire\Livewire;

test('self excluded customers cannot place bids', function () {
    $user = User::factory()->create([
        'phone' => '254712345678',
        'phone_verified_at' => now(),
    ]);

    Bidder::factory()->create([
        'user_id' => $user->id,
        'phone' => $user->phone,
    ]);

    CustomerControl::query()->create([
        'user_id' => $user->id,
        'self_excluded_until' => now()->addDays(30),
    ]);

    $auction = Auction::factory()->create([
        'min_bid' => 10,
        'max_bid' => 1000,
        'bid_increment' => 5,
        'bid_fee' => 50,
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::auctions.show', ['auction' => $auction])
        ->set('name', $user->name)
        ->set('email', $user->email)
        ->set('phone', '0712345678')
        ->set('amount', '15')
        ->set('rulesConsent', true)
        ->call('submitBid')
        ->assertHasErrors(['phone'])
        ->assertSet('formState', 'idle');
});

test('participation fee limit blocks bids that would exceed it', function () {
    $user = User::factory()->create([
        'phone' => '254712345678',
        'phone_verified_at' => now(),
    ]);

    $bidder = Bidder::factory()->create([
        'user_id' => $user->id,
        'phone' => $user->phone,
    ]);

    CustomerControl::query()->create([
        'user_id' => $user->id,
        'participation_fee_limit' => 100,
        'limit_period' => 'daily',
    ]);

    $priorBid = AuctionBid::factory()->accepted()->create([
        'bidder_id' => $bidder->id,
    ]);

    PaymentAttempt::factory()->succeeded()->create([
        'auction_bid_id' => $priorBid->id,
        'amount' => 80,
        'updated_at' => now(),
    ]);

    $auction = Auction::factory()->create([
        'min_bid' => 10,
        'max_bid' => 1000,
        'bid_increment' => 5,
        'bid_fee' => 30,
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::auctions.show', ['auction' => $auction])
        ->set('name', $user->name)
        ->set('email', $user->email)
        ->set('phone', '0712345678')
        ->set('amount', '15')
        ->set('rulesConsent', true)
        ->call('submitBid')
        ->assertHasErrors(['amount'])
        ->assertSet('formState', 'idle');
});

test('logged in customers see prefilled bid details on auction page', function () {
    $user = User::factory()->create([
        'phone' => '254712345678',
        'phone_verified_at' => now(),
        'name' => 'Jane Bidder',
        'email' => 'jane@example.com',
    ]);

    Bidder::factory()->create([
        'user_id' => $user->id,
        'phone' => $user->phone,
        'whatsapp_number' => '254711222333',
    ]);

    $auction = Auction::factory()->create([
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::auctions.show', ['auction' => $auction])
        ->assertSet('name', 'Jane Bidder')
        ->assertSet('email', 'jane@example.com')
        ->assertSet('phone', '0712345678')
        ->assertSet('whatsappNumber', '0711222333');
});
