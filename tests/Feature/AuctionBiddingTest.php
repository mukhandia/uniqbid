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

test('bid form validates name phone amount and consent', function () {
    $auction = Auction::factory()->create([
        'min_bid' => 10,
        'max_bid' => 1000,
        'bid_increment' => 5,
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    Livewire::test('pages::auctions.show', ['auction' => $auction])
        ->set('name', 'A')
        ->set('phone', '12345')
        ->set('amount', '3')
        ->set('consent', false)
        ->call('submitBid')
        ->assertHasErrors(['name', 'phone', 'amount', 'consent']);
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
        ->set('phone', '0712345678')
        ->set('amount', '12')
        ->set('consent', true)
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
        ->set('phone', '0712345678')
        ->set('amount', '15')
        ->set('consent', true)
        ->call('submitBid')
        ->assertHasNoErrors()
        ->assertSet('formState', 'pending');
});

test('forced payment failure can be retried and reset', function () {
    $auction = Auction::factory()->create([
        'min_bid' => 10,
        'max_bid' => 1000,
        'bid_increment' => 5,
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    $component = Livewire::test('pages::auctions.show', ['auction' => $auction])
        ->set('name', 'James Mwangi')
        ->set('phone', '0798765432')
        ->set('amount', '20')
        ->set('consent', true)
        ->set('forceFail', true)
        ->call('submitBid')
        ->assertSet('formState', 'pending')
        ->call('completeSimulatedPayment')
        ->assertSet('formState', 'failure')
        ->call('retryPayment')
        ->assertSet('formState', 'pending')
        ->assertSet('forceFail', false)
        ->call('resetForm')
        ->assertSet('formState', 'idle')
        ->assertSet('reference', '');

    expect($component->get('formState'))->toBe('idle');
});

test('simulated payment can succeed and produce a reference', function () {
    $auction = Auction::factory()->create([
        'min_bid' => 10,
        'max_bid' => 1000,
        'bid_increment' => 5,
        'status' => 'live',
        'ends_at' => now()->addDay(),
    ]);

    $component = Livewire::test('pages::auctions.show', ['auction' => $auction])
        ->set('name', 'James Mwangi')
        ->set('phone', '0798765432')
        ->set('amount', '25')
        ->set('consent', true)
        ->set('forceFail', false)
        ->call('submitBid')
        ->assertSet('formState', 'pending');

    $succeeded = false;

    for ($attempt = 0; $attempt < 40; $attempt++) {
        $component->call('completeSimulatedPayment');

        if ($component->get('formState') === 'success') {
            $succeeded = true;
            break;
        }

        if ($component->get('formState') === 'failure') {
            $component->call('retryPayment');
        }
    }

    expect($succeeded)->toBeTrue();
    expect($component->get('reference'))->toStartWith('UB');
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
