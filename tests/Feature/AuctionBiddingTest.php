<?php

use App\Models\Auction;
use Livewire\Livewire;

test('non live auctions return not found', function () {
    $auction = Auction::factory()->create([
        'status' => 'closed',
        'ends_at' => now()->subHour(),
    ]);

    $this->get(route('auctions.show', $auction))->assertNotFound();
});

test('bid form validates contact details amount and rules consent', function () {
    $auction = Auction::factory()->create([
        'min_bid' => 10,
        'max_bid' => 1000,
        'bid_increment' => 5,
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    Livewire::test('pages::auctions.show', ['auction' => $auction])
        ->set('name', 'A')
        ->set('email', 'not-an-email')
        ->set('phone', '12345')
        ->set('amount', '3')
        ->set('rulesConsent', false)
        ->call('submitBid')
        ->assertHasErrors(['name', 'email', 'phone', 'amount', 'rulesConsent']);
});

test('bid amount must match increment', function () {
    $auction = Auction::factory()->create([
        'min_bid' => 10,
        'max_bid' => 1000,
        'bid_increment' => 5,
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    Livewire::test('pages::auctions.show', ['auction' => $auction])
        ->set('name', 'Amina Wanjiku')
        ->set('email', 'amina@example.com')
        ->set('phone', '0712345678')
        ->set('amount', '12')
        ->set('rulesConsent', true)
        ->call('submitBid')
        ->assertHasErrors(['amount'])
        ->assertSet('formState', 'idle');
});

test('valid bid moves to pending payment state', function () {
    $auction = Auction::factory()->create([
        'min_bid' => 10,
        'max_bid' => 1000,
        'bid_increment' => 5,
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    Livewire::test('pages::auctions.show', ['auction' => $auction])
        ->set('name', 'Amina Wanjiku')
        ->set('email', 'amina@example.com')
        ->set('phone', '0712345678')
        ->set('amount', '15')
        ->set('rulesConsent', true)
        ->call('submitBid')
        ->assertHasNoErrors()
        ->assertSet('formState', 'pending');
});

test('placeholder payment failure can be reset', function () {
    $auction = Auction::factory()->create([
        'min_bid' => 10,
        'max_bid' => 1000,
        'bid_increment' => 5,
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    $component = Livewire::test('pages::auctions.show', ['auction' => $auction])
        ->set('name', 'James Mwangi')
        ->set('email', 'james@example.com')
        ->set('phone', '0798765432')
        ->set('amount', '20')
        ->set('rulesConsent', true)
        ->call('submitBid')
        ->assertSet('formState', 'pending')
        ->call('failPlaceholderPayment')
        ->assertSet('formState', 'failure')
        ->call('resetForm')
        ->assertSet('formState', 'idle')
        ->assertSet('reference', '');

    expect($component->get('formState'))->toBe('idle');
});

test('placeholder payment persists and accepts a bid', function () {
    $auction = Auction::factory()->create([
        'min_bid' => 10,
        'max_bid' => 1000,
        'bid_increment' => 5,
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    $component = Livewire::test('pages::auctions.show', ['auction' => $auction])
        ->set('name', 'James Mwangi')
        ->set('email', 'james@example.com')
        ->set('phone', '0798765432')
        ->set('amount', '25')
        ->set('rulesConsent', true)
        ->call('submitBid')
        ->assertSet('formState', 'pending')
        ->call('completePlaceholderPayment')
        ->assertSet('formState', 'success');

    expect($component->get('reference'))->toStartWith('BID-');
});

test('image selection updates active image index', function () {
    $auction = Auction::factory()->create([
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    $auction->images()->create([
        'url' => '/images/auctions/second.png',
        'label' => 'Second',
        'sort_order' => 1,
    ]);

    Livewire::test('pages::auctions.show', ['auction' => $auction->fresh('images')])
        ->assertSet('activeImage', 0)
        ->call('selectImage', 1)
        ->assertSet('activeImage', 1);
});
